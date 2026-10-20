<?php
declare(strict_types=1);

// Pruebas automatizadas del Micro-HIS sin framework.
// Uso: php pruebas/ejecutar.php [--evidencia=ruta/salida.txt]
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/dobles/LectorQueFalla.php';

use MicroHis\Domain\Filtros;

$lineas = [];
$fallas = 0;
$verificar = static function (string $nombre, bool $paso) use (&$lineas, &$fallas): void {
    $lineas[] = ($paso ? '  OK    ' : '  FALLA ') . $nombre;
    $fallas += $paso ? 0 : 1;
};
$octubre = new Filtros('2026-10-01', '2026-10-31');

$lineas[] = '== 1. Camino feliz (base de prueba micro_his_test) ==';
$caso = crearCasoDeUso('prueba');
$r = $caso->ejecutar('administrador', 'ocupacion', $octubre, 'registros', 'sala', 'Medicina');
$verificar('[camino feliz] responde 200', $r->codigoHttp === 200);
$verificar('[camino feliz] ocupación = 78 % (39 de 50 camas habilitadas)', $r->indicador?->valor === 78.0 && $r->indicador->numerador === 39);
$verificar('[camino feliz] ficha de trazabilidad completa (RN-05)', isset($r->indicador?->trazabilidad['formula'], $r->indicador->trazabilidad['fuente']));
$verificar('[camino feliz] desglose suma ' . $r->desglose?->total() . ' = numerador (RN-07)', $r->desglose?->total() === $r->indicador?->numerador);
$verificar('[camino feliz] Medicina: ' . count($r->registros ?? []) . ' registros = su parte del desglose (RN-07)', count($r->registros ?? []) === ($r->desglose?->partes['Medicina'] ?? -1));
$verificar('[camino feliz] pacientes solo con código PAC-NNNNN (RN-09)', array_reduce($r->registros ?? [], static fn ($ok, $reg) => $ok && preg_match('/^PAC-\d{5}$/', $reg->paciente) === 1, true));

$lineas[] = '== 2. Reglas de dominio ==';
try {
    new Filtros('2026-10-31', '2026-10-01');
    $verificar('[regla de dominio] rechaza un período invertido (RN-02)', false);
} catch (\InvalidArgumentException) {
    $verificar('[regla de dominio] rechaza un período invertido (RN-02)', true);
}
$verificar('[regla de dominio] el Médico no puede ver Ocupación: 403 (RN-01)', $caso->ejecutar('medico', 'ocupacion', $octubre)->codigoHttp === 403);
$verificar('[regla de dominio] período sin datos informa «Sin datos», no cero (RN-06)', $caso->ejecutar('administrador', 'ocupacion', new Filtros('2025-01-01', '2025-01-31'))->estado === 'sin_datos');

$lineas[] = '== 3. Error de persistencia (doble LectorQueFalla) ==';
$conFalla = crearCasoDeUso(lector: new LectorQueFalla());
$r = $conFalla->ejecutar('administrador', 'ocupacion', $octubre);
$verificar('[error de persistencia] responde 503 controlado', $r->codigoHttp === 503 && $r->estado === 'error');
$verificar('[error de persistencia] el mensaje no expone detalles técnicos', preg_match('/SQLSTATE|PDO|pgsql|55440|127\.0\.0\.1/i', $r->mensaje) === 0);

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
