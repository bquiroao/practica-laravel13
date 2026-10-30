<?php
declare(strict_types=1);

// Semana 15: pruebas unitarias, de caja blanca y de caja negra, trazadas a RF/RNF.
// Uso: php pruebas/semana15.php [--evidencia=ruta/salida.txt]
// Las pruebas marcadas [DEFECTO] describen el comportamiento esperado que hoy NO se cumple.
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/dobles/RepositorioQueFalla.php';

use MicroHis\Domain\{Desglose, Filtros, Indicador, RegistroOrigen, ReporteOcupacion, SinDatos};
use MicroHis\Persistence\OcupacionInMemoryRepository;

$lineas = [];
$fallas = 0;
$verificar = static function (string $id, string $nombre, bool $paso) use (&$lineas, &$fallas): void {
    $lineas[] = ($paso ? '  OK    ' : '  FALLA ') . "{$id} {$nombre}";
    $fallas += $paso ? 0 : 1;
};
$lanza = static function (callable $accion, string $clase): bool {
    try {
        $accion();
        return false;
    } catch (\Throwable $e) {
        return $e instanceof $clase;
    }
};
$ficha = ['formula' => 'f', 'periodo' => 'p', 'filtros' => 'x', 'fuente' => 's', 'calculado_en' => 'c'];
$octubre = new Filtros('2026-10-01', '2026-10-31');

// Mini repositorio en memoria: 3 camas habilitadas y 1 en mantenimiento.
$camas = ['A-1' => ['sala' => 'A', 'habilitada' => true], 'A-2' => ['sala' => 'A', 'habilitada' => true],
          'B-1' => ['sala' => 'B', 'habilitada' => true], 'B-9' => ['sala' => 'B', 'habilitada' => false]];
$estancia = static fn (string $cama, string $paciente, string $ingreso, ?string $egreso) =>
    ['cama' => $cama, 'paciente' => $paciente, 'ingreso' => $ingreso, 'egreso' => $egreso];

$lineas[] = '== Unitarias: reglas, agregación y precisión (Domain) ==';
$verificar('U-01', 'Filtros rechaza formato inválido, fecha imposible y período invertido (RF-02, RN-02)',
    $lanza(fn () => new Filtros('01/10/2026', '2026-10-31'), \InvalidArgumentException::class)
    && $lanza(fn () => new Filtros('2026-02-30', '2026-03-01'), \InvalidArgumentException::class)
    && $lanza(fn () => new Filtros('2026-10-31', '2026-10-01'), \InvalidArgumentException::class)
    && (new Filtros('2026-10-01', '2026-10-01'))->periodo() === '2026-10-01 a 2026-10-01');
$verificar('U-02', 'Indicador rechaza % fuera de 0-100 y ficha incompleta (RF-01, RF-03, RN-05)',
    $lanza(fn () => new Indicador('x', 100.1, '%', 1, $ficha), \DomainException::class)
    && $lanza(fn () => new Indicador('x', 50.0, '%', 1, ['formula' => 'f']), \DomainException::class)
    && (new Indicador('x', 100.0, '%', 1, $ficha))->valor === 100.0);
$verificar('U-03', 'Desglose suma sus partes y rechaza valores negativos (RF-04, RN-07)',
    (new Desglose('sala', ['A' => 2, 'B' => 1]))->total() === 3
    && $lanza(fn () => new Desglose('sala', ['A' => -1]), \DomainException::class));
$verificar('U-04', 'RegistroOrigen acepta solo código de paciente PAC-NNNNN (RF-05, RN-09, RNF-03)',
    $lanza(fn () => new RegistroOrigen('A-1', 'Juan Pérez', '2026-10-01', null), \DomainException::class)
    && (new RegistroOrigen('A-1', 'PAC-00001', '2026-10-01', null))->paciente === 'PAC-00001');
$dosDeTres = new ReporteOcupacion(new OcupacionInMemoryRepository($camas, [
    $estancia('A-1', 'PAC-00001', '2026-10-02', null), $estancia('B-1', 'PAC-00002', '2026-10-03', '2026-10-05')]));
$verificar('U-05', 'Precisión: 2 de 3 camas = 66.7 % redondeado a un decimal (RF-01)',
    $dosDeTres->calcular($octubre)->valor === 66.7);
$verificar('U-06', 'Período sin estancias lanza SinDatos y nunca devuelve 0 (RN-06)',
    $lanza(fn () => $dosDeTres->calcular(new Filtros('2025-01-01', '2025-01-31')), SinDatos::class));

$lineas[] = '== Caja blanca: ramas y condiciones ==';
$caso = crearCasoDeUso(repositorio: new OcupacionInMemoryRepository($camas, [$estancia('A-1', 'PAC-00001', '2026-10-02', null)]));
$ramas = [
    'nivel inválido → 422' => $caso->ejecutar('administrador', 'ocupacion', $octubre, 'otro')->codigoHttp === 422,
    'rol sin permiso → 403' => $caso->ejecutar('medico', 'ocupacion', $octubre)->codigoHttp === 403,
    'reporte no registrado → 404' => $caso->ejecutar('administrador', 'mortalidad', $octubre)->codigoHttp === 404,
    'sin datos → sin_datos' => $caso->ejecutar('administrador', 'ocupacion', new Filtros('2025-01-01', '2025-01-02'))->estado === 'sin_datos',
    'nivel indicador → sin desglose' => $caso->ejecutar('administrador', 'ocupacion', $octubre)->desglose === null,
    'nivel desglose → sin registros' => $caso->ejecutar('administrador', 'ocupacion', $octubre, 'desglose')->registros === null,
    'registros sin parte → 422' => $caso->ejecutar('administrador', 'ocupacion', $octubre, 'registros')->codigoHttp === 422,
    'dimensión no declarada → 422' => $caso->ejecutar('administrador', 'ocupacion', $octubre, 'desglose', 'medico')->codigoHttp === 422,
    'fuente caída → 503' => crearCasoDeUso(repositorio: new RepositorioQueFalla())->ejecutar('administrador', 'ocupacion', $octubre)->codigoHttp === 503,
];
$malas = array_keys(array_filter($ramas, static fn ($r) => !$r));
$verificar('CB-01', 'CalcularIndicador::ejecutar recorre sus 9 caminos' . ($malas ? ' (fallan: ' . implode(', ', $malas) . ')' : ''), $malas === []);
$condicion = new OcupacionInMemoryRepository($camas, [
    $estancia('A-1', 'PAC-00001', '2026-09-01', null),         // egreso nulo, empezó antes → cuenta
    $estancia('A-2', 'PAC-00002', '2026-09-01', '2026-10-01'), // egreso = desde (límite) → cuenta
    $estancia('B-1', 'PAC-00003', '2026-09-01', '2026-09-30'), // egreso < desde → no cuenta
    $estancia('B-9', 'PAC-00004', '2026-10-05', null),         // cama en mantenimiento → no cuenta
    $estancia('B-1', 'PAC-00005', '2026-11-01', null),         // ingreso > hasta → no cuenta
]);
$verificar('CB-02', 'Condición «estancia en el período»: cada término cambia el resultado (A=2, B=0)',
    $condicion->ocupadasPorSala($octubre) === ['A' => 2]);

$lineas[] = '== Caja negra: API v1 con la base de prueba (equivalencia, límites, permisos y errores) ==';
$api = static function (string $ruta, array $p, ?object $repo = null): array {
    $r = crearEnrutador(crearCasoDeUso('prueba', $repo))->despachar('GET', $ruta, $p + ['correlacion' => 's15']);
    return [$r->codigo, json_decode($r->cuerpo, true)];
};
$base = ['desde' => '2026-10-01', 'hasta' => '2026-10-31', 'rol' => 'jefe_area'];
$ruta = '/api/v1/reportes/ocupacion';
$verificar('CN-01', 'Equivalencia del período: válido 200; invertido, formato inválido y fecha imposible 422 (RF-02)',
    $api($ruta, $base)[0] === 200 && $api($ruta, ['desde' => '2026-10-31', 'hasta' => '2026-10-01'] + $base)[0] === 422
    && $api($ruta, ['desde' => '1/10/2026'] + $base)[0] === 422 && $api($ruta, ['desde' => '2026-02-30'] + $base)[0] === 422);
[, $unDia] = $api($ruta, ['desde' => '2026-10-01', 'hasta' => '2026-10-01'] + $base);
[, $ultimoDia] = $api($ruta, ['desde' => '2026-09-20', 'hasta' => '2026-09-20'] + $base);
[, $diaSiguiente] = $api($ruta, ['desde' => '2026-09-21', 'hasta' => '2026-09-21'] + $base);
$verificar('CN-02', 'Límites: un solo día 2.0 %; egreso el mismo día cuenta (2.0 %); día siguiente sin datos (RF-01, RN-06)',
    ($unDia['datos']['indicador']['valor'] ?? null) == 2.0 && ($ultimoDia['datos']['indicador']['valor'] ?? null) == 2.0
    && ($diaSiguiente['estado'] ?? null) === 'sin_datos');
$verificar('CN-03', 'Permisos: Administrador y Jefe 200; Médico y rol desconocido 403 SIN_PERMISO (RF-07, RNF-02)',
    $api($ruta, ['rol' => 'administrador'] + $base)[0] === 200 && $api($ruta, $base)[0] === 200
    && $api($ruta, ['rol' => 'medico'] + $base)[1]['error']['codigo'] === 'SIN_PERMISO'
    && $api($ruta, ['rol' => 'invitado'] + $base)[0] === 403);
$verificar('CN-04', 'Errores: tipo inexistente 404, dimensión inválida 422, registros sin parte 422, fuente caída 503 sin detalles (RNF-02)',
    $api('/api/v1/reportes/inexistente', $base)[0] === 404
    && $api("{$ruta}/desglose", ['dimension' => 'medico'] + $base)[0] === 422
    && $api("{$ruta}/registros", $base)[0] === 422
    && ($caida = $api($ruta, $base, new RepositorioQueFalla()))[0] === 503 && !str_contains(json_encode($caida[1]), 'SQLSTATE'));
$verificar('CN-05', '[DEFECTO D-01] Una parte que no existe en el desglose debe responder 422, no 200 vacío (RF-05, RN-07)',
    $api("{$ruta}/registros", ['parte' => 'Urgencias'] + $base)[0] === 422);
$verificar('CN-06', '[DEFECTO D-02] Un período de más de 366 días debe responder 422, como dice el contrato API (RNF-01)',
    $api($ruta, ['desde' => '2024-01-01', 'hasta' => '2026-10-31'] + $base)[0] === 422);

$total = count(array_filter($lineas, static fn ($l) => !str_starts_with($l, '==')));
$lineas[] = '';
$lineas[] = "RESULTADO: {$total} pruebas, " . ($total - $fallas) . " correctas, {$fallas} con defecto registrado";
$salida = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $salida;
foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--evidencia=')) {
        file_put_contents(substr($argumento, 12), 'Ejecutado: ' . date('Y-m-d H:i') . ' · PHP ' . PHP_VERSION . PHP_EOL . PHP_EOL . $salida);
    }
}
exit($fallas === 0 ? 0 : 1);
