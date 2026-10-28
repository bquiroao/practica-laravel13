<?php

declare(strict_types=1);

/**
 * Semana 15 · Mide la cobertura de LÍNEAS que logran SOLO las pruebas de caja blanca (CB-01..CB-04)
 * sobre las clases del flujo que diseñan: AutorizarOperacion, PoliticaAutorizacion, Membresia y Kernel.
 *
 * Uso (requiere phpdbg, que viene con PHP):
 *   phpdbg -qrr herramientas/cobertura-caja-blanca.php      (desde docs/modulos/rbac/semana-15)
 */
if (!function_exists('phpdbg_start_oplog')) {
    fwrite(STDERR, "Ejecutar con phpdbg -qrr (php normal no expone la cobertura).\n");
    exit(2);
}

$micro = realpath(dirname(__DIR__, 2) . '/micro-his-rbac');
require $micro . '/bootstrap/contenedor.php';
require $micro . '/tests/Arnes.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

$objetivos = [
    'src/Aplicacion/CasosDeUso/AutorizarOperacion.php',
    'src/Dominio/Servicios/PoliticaAutorizacion.php',
    'src/Dominio/Modelo/Membresia.php',
    'src/Presentacion/Http/Kernel.php',
];
$rutas = array_map(static fn (string $r): string => realpath("$micro/$r"), $objetivos);

phpdbg_start_oplog();
require $micro . '/tests/Aplicacion/CajaBlancaPrueba.php';
$ejecutadas = phpdbg_end_oplog();
$ejecutables = phpdbg_get_executable(['files' => $rutas]);

echo "Pruebas de caja blanca" . PHP_EOL;
foreach ($GLOBALS['resultados'] as $r) {
    printf("[%-5s] %-6s %s\n", $r['estado'], $r['id'], $r['descripcion']);
    if ($r['detalle'] !== null) {
        echo "        -> {$r['detalle']}\n";
    }
}

echo PHP_EOL . "Cobertura de líneas lograda por CB-01..CB-04" . PHP_EOL . str_repeat('-', 96) . PHP_EOL;
$totalE = 0;
$totalC = 0;
foreach ($objetivos as $i => $relativa) {
    $ruta = $rutas[$i];
    $fuente = file($ruta);
    // Líneas ejecutables reales: se descartan las que phpdbg marca pero no tienen sentencia (llaves, firmas).
    $ejec = array_keys(array_filter($ejecutables[$ruta] ?? [], static fn ($v, int $l): bool
        => !preg_match('/^\s*(\{|\};?|\);?|\),?|(public|private|protected)( static)? function|final class|namespace |use |declare\()/', $fuente[$l - 1] ?? ''), ARRAY_FILTER_USE_BOTH));
    $cub = array_values(array_filter($ejec, static fn (int $l): bool => isset($ejecutadas[$ruta][$l])));
    $sin = array_values(array_diff($ejec, $cub));
    $totalE += count($ejec);
    $totalC += count($cub);
    printf("%-52s %3d/%-3d  %5.1f %%\n", $relativa, count($cub), count($ejec), count($ejec) ? 100 * count($cub) / count($ejec) : 100);
    foreach ($sin as $l) {
        printf("      sin cubrir  l.%-4d %s\n", $l, trim($fuente[$l - 1]));
    }
}
echo str_repeat('-', 96) . PHP_EOL;
printf("TOTAL                                                %3d/%-3d  %5.1f %%\n", $totalC, $totalE, 100 * $totalC / max(1, $totalE));
