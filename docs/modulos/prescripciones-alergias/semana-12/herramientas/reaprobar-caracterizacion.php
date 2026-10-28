<?php

declare(strict_types=1);

/**
 * Semana 12 · Re-aprobación CONTROLADA de la caracterización de la semana 7 (tests/Caracterizacion/esperado.json).
 * El cambio agrega «puede_justificar» a la previa con ADVERTENCIA: CAR-01 lo detecta, como debe. Antes de regrabar,
 * esta herramienta compara los 32 escenarios y SOLO regraba si la única diferencia es esa clave nueva; si cambió
 * cualquier otra cosa, se niega y lo lista.
 *   php herramientas/reaprobar-caracterizacion.php            (solo informa)
 *   php herramientas/reaprobar-caracterizacion.php --grabar   (regraba si la diferencia es la esperada)
 */
$micro = dirname(__DIR__, 2) . '/micro-his-prescripciones';
require $micro . '/bootstrap/contenedor.php';
require $micro . '/tests/Arnes.php';
foreach (glob($micro . '/tests/Dobles/*.php') ?: [] as $archivo) {
    require_once $archivo;
}
require $micro . '/tests/Caracterizacion/escenarios.php';

$archivo = $micro . '/tests/Caracterizacion/esperado.json';
$esperado = json_decode((string) file_get_contents($archivo), true, 32, JSON_THROW_ON_ERROR);
$actual = json_decode((string) json_encode(caracterizar(), JSON_PRESERVE_ZERO_FRACTION), true);

$cambiados = $otros = [];
foreach ($esperado as $nombre => $antes) {
    $ahora = $actual[$nombre] ?? null;
    if ($ahora === $antes) {
        continue;
    }
    // ¿La única diferencia es la clave nueva en el cuerpo?
    $sinClave = $ahora;
    unset($sinClave['cuerpo']['puede_justificar']);
    if ($sinClave === $antes && is_bool($ahora['cuerpo']['puede_justificar'] ?? null)) {
        $cambiados[] = sprintf('%-48s + puede_justificar: %s (codigo %s)', $nombre, var_export($ahora['cuerpo']['puede_justificar'], true), $ahora['cuerpo']['codigo']);
    } else {
        $otros[] = $nombre;
    }
}
if (array_diff_key($actual, $esperado) !== []) {
    $otros[] = 'escenarios nuevos: ' . implode(', ', array_keys(array_diff_key($actual, $esperado)));
}
echo 'Escenarios: ' . count($esperado) . '  Iguales: ' . (count($esperado) - count($cambiados) - count($otros)) . '  Solo la clave nueva: ' . count($cambiados) . '  Otras diferencias: ' . count($otros) . PHP_EOL;
foreach ($cambiados as $l) {
    echo "  $l" . PHP_EOL;
}
foreach ($otros as $l) {
    echo "  OTRA DIFERENCIA: $l" . PHP_EOL;
}
if ($otros !== []) {
    echo 'No se regraba: hay diferencias que el cambio no explica.' . PHP_EOL;
    exit(1);
}
if (in_array('--grabar', $argv, true)) {
    file_put_contents($archivo, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) . "\n");
    echo 'Regrabado: ' . basename($archivo) . PHP_EOL;
}
