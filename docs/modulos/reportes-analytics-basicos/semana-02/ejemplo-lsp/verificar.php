<?php
declare(strict_types=1);

/* Evidencia verificable de LSP:  php verificar.php
 * Aplica las MISMAS pruebas de contrato a cada reporte: si uno no es sustituible, la prueba lo detecta. */
require_once __DIR__ . '/antes/Reportes.php';
require_once __DIR__ . '/despues/Reportes.php';

echo "== ANTES: el cliente usa la clase base sin parches ==\n";
foreach ([new ReporteOcupacionAntes(), new ReporteCitasAntes(), new ReporteMortalidadAntes()] as $r) {
    try {
        echo '  ' . get_class($r) . ': valor=' . $r->calcular([]) . ', suma_desglose=' . array_sum($r->desglosar([], 'sala')) . "\n";
    } catch (Throwable $e) {
        echo '  ' . get_class($r) . ": ERROR -> {$e->getMessage()}\n";
    }
}

echo "\n== DESPUÉS: pruebas de contrato iguales para todos los reportes ==\n";
$registro = new ReportRegistry();
array_map([$registro, 'registrar'], [new ReporteOcupacion(), new ReporteCitas(), new ReporteMortalidad()]);
$filtros = new Filtros('2026-10-01', '2026-10-31');
$fallas = 0;

foreach ($registro->todos() as $r) {
    $i = $r->calcular($filtros);
    $d = $r->desglosar($filtros, $r->dimensiones()[0]);
    $pruebas = [
        'porcentaje entre 0 y 100' => $i->unidad !== '%' || ($i->valor >= 0 && $i->valor <= 100),
        'ficha de trazabilidad (RN-05)' => isset($i->trazabilidad['formula'], $i->trazabilidad['periodo'], $i->trazabilidad['fuente']),
        'declara su dimensión' => $r->dimensiones() !== [],
        "suma del desglose {$d->total()} = indicador {$i->numerador} (RN-07)" => $d->total() === $i->numerador,
        'el cliente lo usa sin instanceof' => str_starts_with(mostrarTrazable($r, $filtros), 'valor='),
    ];
    foreach ($pruebas as $nombre => $paso) {
        echo ($paso ? '  OK    ' : '  FALLA ') . "[{$r->tipo()}] {$nombre}\n";
        $fallas += $paso ? 0 : 1;
    }
}

try {
    new Filtros('2026-10-31', '2026-10-01');
    echo "  FALLA [dominio] acepta un período invertido\n";
    $fallas++;
} catch (InvalidArgumentException) {
    echo "  OK    [dominio] rechaza un período invertido (RN-02)\n";
}

echo "\n" . ($fallas === 0 ? 'RESULTADO: todos los reportes son sustituibles (LSP cumplido)' : "RESULTADO: {$fallas} falla(s)") . "\n";
exit($fallas === 0 ? 0 : 1);
