<?php

declare(strict_types=1);

/*
 * Semana 2 · ¿Los criterios de aceptación detectan si la regla se rompe? Cada mutación copia despues/, cambia UNA línea
 * de la regla de calidad y corre verificar.php sobre la copia: debe fallar al menos un criterio.
 *   php mutaciones.php      Código de salida: mutaciones que sobreviven.
 */

$archivo = 'src/Dominio/QualityGate.php';
$mutaciones = [
    'M1 un crítico INESTABLE cuenta como PASA (R-06)' => ['$r->critico && $r->resultado !== Resultado::PASA', '$r->critico && !in_array($r->resultado, [Resultado::PASA, Resultado::INESTABLE], true)'],
    'M2 no se compara el digest (R-03)' => ["'digest' => \$ejecucion->digest === \$digestVersion", "'digest' => true"],
    'M3 la evidencia no se confirma en el almacén (R-05)' => ['count(array_filter($r->evidencias, $evidenciaConfirmada)) < count($r->evidencias)', 'false'],
    'M4 el umbral es estricto (> en vez de ≥)' => ['$tasa >= $politica->umbralNoCriticos', '$tasa > $politica->umbralNoCriticos'],
    'M5 los OMITIDOS salen de la tasa' => ['            if (!$r->critico) {', '            if (!$r->critico && $r->resultado !== Resultado::OMITIDO) {'],
    'M6 la antigüedad no tiene límite (R-09e)' => ['$horas <= $politica->antiguedadMaximaHoras', 'true'],
    'M7 se evalúa una ejecución no completada' => ["if (!\$ejecucion->completada) {\n            throw", "if (false) {\n            throw"],
    'M8 se permite un inestable más que el máximo (R-09c)' => ['$inestables <= $politica->maxInestables', '$inestables <= $politica->maxInestables + 1'],
];

$sobreviven = 0;
foreach ($mutaciones as $nombre => [$buscar, $reemplazo]) {
    $copia = sys_get_temp_dir() . '/e2e-mut-' . getmypid() . '-' . md5($nombre);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/despues', FilesystemIterator::SKIP_DOTS)) as $f) {
        $destino = $copia . substr((string) $f, strlen(__DIR__ . '/despues'));
        @mkdir(dirname($destino), 0777, true);
        copy((string) $f, $destino);
    }
    $codigo = str_replace("\r\n", "\n", (string) file_get_contents("$copia/$archivo"));
    if (substr_count($codigo, $buscar) !== 1) {
        exit("$nombre: el patrón no aparece exactamente una vez\n");
    }
    file_put_contents("$copia/$archivo", str_replace($buscar, $reemplazo, $codigo));
    $salida = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/verificar.php') . ' ' . escapeshellarg($copia) . ' 2>&1', $salida, $codigoSalida);
    $fallan = array_map(static fn (string $l): string => trim(substr($l, 8, 7)), preg_grep('/^\[FALLA /', $salida) ?: []);
    $sobreviven += $codigoSalida === 0 ? 1 : 0;
    printf("[%s] %-62s %s\n", $codigoSalida === 0 ? 'SOBREVIVE' : 'DETECTA  ', $nombre, $fallan === [] ? '' : 'falla: ' . implode(', ', $fallan));
}
echo "\nMutaciones: " . count($mutaciones) . "  Detectadas: " . (count($mutaciones) - $sobreviven) . "  Sobreviven: $sobreviven\n";
exit($sobreviven);
