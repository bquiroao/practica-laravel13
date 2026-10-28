<?php
declare(strict_types=1);

// Pruebas automatizadas del Micro-HIS sin framework.
// Uso: php pruebas/ejecutar.php [--evidencia=ruta/salida.txt]
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/dobles/RepositorioQueFalla.php';

use MicroHis\Domain\Filtros;
use MicroHis\Persistence\ConexionPdo;
use MicroHis\Persistence\OcupacionPdoRepository;

$lineas = [];
$fallas = 0;
$verificar = static function (string $nombre, bool $paso) use (&$lineas, &$fallas): void {
    $lineas[] = ($paso ? '  OK    ' : '  FALLA ') . $nombre;
    $fallas += $paso ? 0 : 1;
};
$octubre = new Filtros('2026-10-01', '2026-10-31');
$pdo = new OcupacionPdoRepository(new ConexionPdo(__DIR__ . '/../config/app.ini', 'prueba'));
$memoria = require __DIR__ . '/datos/ocupacion-octubre.php';

foreach (['PDO (micro_his_test)' => $pdo, 'InMemory' => $memoria] as $nombre => $repositorio) {
    $lineas[] = "== 1. Camino feliz con el adaptador {$nombre} ==";
    $r = crearCasoDeUso(repositorio: $repositorio)->ejecutar('administrador', 'ocupacion', $octubre, 'registros', 'sala', 'Medicina');
    $verificar('[camino feliz] responde 200', $r->codigoHttp === 200);
    $verificar('[camino feliz] ocupación = 78 % (39 de 50 camas habilitadas)', $r->indicador?->valor === 78.0 && $r->indicador->numerador === 39);
    $verificar('[camino feliz] ficha de trazabilidad completa (RN-05)', isset($r->indicador?->trazabilidad['formula'], $r->indicador->trazabilidad['fuente']));
    $verificar('[camino feliz] desglose suma ' . $r->desglose?->total() . ' = numerador (RN-07)', $r->desglose?->total() === $r->indicador?->numerador);
    $verificar('[camino feliz] Medicina: ' . count($r->registros ?? []) . ' registros = su parte del desglose (RN-07)', count($r->registros ?? []) === ($r->desglose?->partes['Medicina'] ?? -1));
    $verificar('[camino feliz] pacientes solo con código PAC-NNNNN (RN-09)', array_reduce($r->registros ?? [], static fn ($ok, $reg) => $ok && preg_match('/^PAC-\d{5}$/', $reg->paciente) === 1, true));
}

$lineas[] = '== 2. Repository: los dos adaptadores son intercambiables ==';
try {
    $verificar('[repository] PDO e InMemory dan el mismo desglose', $pdo->ocupadasPorSala($octubre) === $memoria->ocupadasPorSala($octubre));
    $verificar('[repository] PDO e InMemory dan los mismos registros de Cirugía', $pdo->registrosDeSala($octubre, 'Cirugía') == $memoria->registrosDeSala($octubre, 'Cirugía'));
} catch (\MicroHis\Domain\DatosNoDisponibles) {
    $verificar('[repository] la base de prueba no responde: ¿corre «docker compose up -d»?', false);
}

$lineas[] = '== 3. Reglas de dominio ==';
$caso = crearCasoDeUso(repositorio: $memoria);
try {
    new Filtros('2026-10-31', '2026-10-01');
    $verificar('[regla de dominio] rechaza un período invertido (RN-02)', false);
} catch (\InvalidArgumentException) {
    $verificar('[regla de dominio] rechaza un período invertido (RN-02)', true);
}
$verificar('[regla de dominio] el Médico no puede ver Ocupación: 403 (RN-01)', $caso->ejecutar('medico', 'ocupacion', $octubre)->codigoHttp === 403);
$verificar('[regla de dominio] período sin datos informa «Sin datos», no cero (RN-06)', $caso->ejecutar('administrador', 'ocupacion', new Filtros('2025-01-01', '2025-01-31'))->estado === 'sin_datos');

$lineas[] = '== 4. Error de persistencia (doble RepositorioQueFalla) ==';
$r = crearCasoDeUso(repositorio: new RepositorioQueFalla())->ejecutar('administrador', 'ocupacion', $octubre);
$verificar('[error de persistencia] responde 503 controlado', $r->codigoHttp === 503 && $r->estado === 'error');
$verificar('[error de persistencia] el mensaje no expone detalles técnicos', preg_match('/SQLSTATE|PDO|pgsql|55440|127\.0\.0\.1/i', $r->mensaje) === 0);

$lineas[] = '== 5. MVC: el controlador solo coordina ==';
$enrutador = crearEnrutador($caso);
$respuesta = $enrutador->despachar('GET', '/', ['nivel' => 'desglose']);
$verificar('[mvc] GET / responde 200 y la vista muestra 78 %', $respuesta->codigo === 200 && str_contains($respuesta->cuerpo, '78 %'));
$verificar('[mvc] fecha inválida: el controlador responde 422', $enrutador->despachar('GET', '/', ['desde' => '2026-13-01'])->codigo === 422);
$verificar('[mvc] ruta desconocida responde 404', $enrutador->despachar('GET', '/otra', [])->codigo === 404);
$controladores = implode('', array_map('file_get_contents', glob(__DIR__ . '/../src/Presentation/Controller/*.php')));
$verificar('[mvc] los controladores no contienen SQL ni PDO', preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|PDO)\b/', $controladores) === 0);

$lineas[] = '== 6. API REST v1 (cliente-servidor) ==';
$api = static fn (string $ruta, array $p) => crearEnrutador($caso)->despachar('GET', $ruta, $p + ['correlacion' => 'prueba-123']);
$base = ['desde' => '2026-10-01', 'hasta' => '2026-10-31', 'rol' => 'administrador'];
$json = json_decode($api('/api/v1/reportes/ocupacion/desglose', $base)->cuerpo, true);
$verificar('[api] GET /api/v1/reportes/ocupacion/desglose: 78 % y total 39', ($json['datos']['indicador']['valor'] ?? null) == 78 && ($json['datos']['desglose']['total'] ?? null) === 39);
$respuesta = $api('/api/v1/reportes/ocupacion/registros', $base + ['parte' => 'Pediatría']);
$verificar('[api] /registros de Pediatría devuelve 7 registros', count(json_decode($respuesta->cuerpo, true)['datos']['registros'] ?? []) === 7);
$verificar('[api] devuelve JSON y repite el X-Correlation-Id', str_starts_with($respuesta->cabeceras['Content-Type'], 'application/json') && $respuesta->cabeceras['X-Correlation-Id'] === 'prueba-123');
$respuesta = $api('/api/v1/reportes/ocupacion', ['rol' => 'medico'] + $base);
$verificar('[api] rol sin permiso: 403 con código SIN_PERMISO', $respuesta->codigo === 403 && json_decode($respuesta->cuerpo, true)['error']['codigo'] === 'SIN_PERMISO');
$verificar('[api] período inválido: 422 con código VALIDACION', $api('/api/v1/reportes/ocupacion', ['hasta' => '2026-09-01'] + $base)->codigo === 422);
$conFalla = crearEnrutador(crearCasoDeUso(repositorio: new RepositorioQueFalla()))->despachar('GET', '/api/v1/reportes/ocupacion', $base);
$verificar('[api] base caída: 503 con código DATOS_NO_DISPONIBLES', $conFalla->codigo === 503 && str_contains($conFalla->cuerpo, 'DATOS_NO_DISPONIBLES'));

$total = count(array_filter($lineas, static fn ($l) => !str_starts_with($l, '==')));
$lineas[] = '';
$lineas[] = $fallas === 0 ? "RESULTADO: {$total} pruebas, todas correctas" : "RESULTADO: {$fallas} de {$total} pruebas fallaron";
$salida = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $salida;

foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--evidencia=')) {
        $cabecera = 'Ejecutado: ' . date('Y-m-d H:i') . ' · PHP ' . PHP_VERSION . PHP_EOL . PHP_EOL;
        file_put_contents(substr($argumento, 12), $cabecera . $salida);
    }
}
exit($fallas === 0 ? 0 : 1);
