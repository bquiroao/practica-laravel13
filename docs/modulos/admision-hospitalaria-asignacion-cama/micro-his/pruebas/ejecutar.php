<?php
declare(strict_types=1);

// Ejecutor propio de pruebas, sin dependencias.
// Uso (desde micro-his/):  php pruebas/ejecutar.php [--grupo=unitarias] [--evidencia=../semana-0N/evidencia/salida-pruebas.txt]
// Cada archivo pruebas/<grupo>/prueba-*.php devuelve ['nombre' => function () {...}, ...].
// Una prueba cuyo nombre empieza con «[DEFECTO D-NN]» documenta un defecto abierto: se espera que falle.

require __DIR__ . '/../autoload.php';
foreach (glob(__DIR__ . '/soporte/*.php') ?: [] as $soporte) {
    require_once $soporte;
}

use Pruebas\PruebaOmitida;

$opciones = ['grupo' => null, 'evidencia' => null];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(grupo|evidencia)=(.+)$/', $arg, $m) === 1) {
        $opciones[$m[1]] = $m[2];
    }
}

$archivos = glob(__DIR__ . '/*/prueba-*.php') ?: [];
sort($archivos);
if ($opciones['grupo'] !== null) {
    $archivos = array_values(array_filter($archivos, fn ($a) => basename(dirname($a)) === $opciones['grupo']));
}

$lineas = [
    'Micro-HIS Admisión hospitalaria y asignación de cama — pruebas',
    'PHP ' . PHP_VERSION . ' · ' . gmdate('Y-m-d H:i') . ' UTC' . ($opciones['grupo'] ? ' · grupo ' . $opciones['grupo'] : ''),
    '',
];
$cuenta = ['ok' => 0, 'falla' => 0, 'omitida' => 0, 'defecto' => 0];

foreach ($archivos as $archivo) {
    // Cada archivo se carga en su propio ámbito para que sus variables no choquen.
    $pruebas = (static fn (string $ruta): array => require $ruta)($archivo);
    $lineas[] = '# ' . basename(dirname($archivo)) . '/' . basename($archivo);
    foreach ($pruebas as $nombre => $prueba) {
        $esDefecto = str_starts_with($nombre, '[DEFECTO ');
        try {
            $prueba();
            if ($esDefecto) {
                $lineas[] = "  [FALLA]   {$nombre} -> ya no falla: actualizar el registro de defectos";
                $cuenta['falla']++;
            } else {
                $lineas[] = "  [OK]      {$nombre}";
                $cuenta['ok']++;
            }
        } catch (PruebaOmitida $e) {
            $lineas[] = "  [OMITIDA] {$nombre} -> {$e->getMessage()}";
            $cuenta['omitida']++;
        } catch (\Throwable $e) {
            if ($esDefecto) {
                $lineas[] = "  [DEFECTO] {$nombre} -> sigue abierto: {$e->getMessage()}";
                $cuenta['defecto']++;
            } else {
                $lineas[] = "  [FALLA]   {$nombre} -> " . get_class($e) . ': ' . $e->getMessage();
                $cuenta['falla']++;
            }
        }
    }
}

$total = array_sum($cuenta);
$lineas[] = '';
$lineas[] = "Total: {$total} · OK: {$cuenta['ok']} · Fallas: {$cuenta['falla']} · Omitidas: {$cuenta['omitida']} · Defectos abiertos: {$cuenta['defecto']}";
$salida = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $salida;

if ($opciones['evidencia'] !== null) {
    // Se escribe con PHP para que quede en UTF-8 (el > de PowerShell 5 escribe UTF-16).
    $carpeta = dirname($opciones['evidencia']);
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }
    file_put_contents($opciones['evidencia'], $salida);
    echo "Evidencia guardada en {$opciones['evidencia']}" . PHP_EOL;
}
exit($cuenta['falla'] === 0 ? 0 : 1);
