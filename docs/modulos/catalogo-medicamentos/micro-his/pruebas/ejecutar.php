<?php
declare(strict_types=1);

/*
 * Ejecutor de pruebas propio (sin PHPUnit).
 *   php pruebas/ejecutar.php [--evidencia=ruta.txt] [--sin-bd] [--grupo=unitarias]
 * Cada archivo de pruebas devuelve un arreglo nombre => función y se carga en su propio ámbito.
 * Los archivos de pruebas/integracion usan la base de prueba (config.php); --sin-bd los omite.
 */

require __DIR__ . '/../autoload.php';
require __DIR__ . '/Afirmar.php';
foreach (glob(__DIR__ . '/dobles/*.php') as $doble) {
    require_once $doble;
}

$opciones = ['evidencia' => null, 'sin-bd' => false, 'grupo' => null];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(evidencia|grupo)=(.+)$/', $arg, $m) === 1) {
        $opciones[$m[1]] = $m[2];
    } elseif ($arg === '--sin-bd') {
        $opciones['sin-bd'] = true;
    }
}

$grupos = array_values(array_filter(glob(__DIR__ . '/*', GLOB_ONLYDIR), fn ($d) => basename($d) !== 'dobles'));
$lineas = ['Pruebas del Micro-HIS Catálogo de medicamentos', 'Fecha: ' . date('Y-m-d H:i:s T') . ' · PHP ' . PHP_VERSION, ''];
$total = $fallas = $omitidas = 0;

foreach ($grupos as $dir) {
    $grupo = basename($dir);
    if ($opciones['grupo'] !== null && $opciones['grupo'] !== $grupo) {
        continue;
    }
    foreach (glob("$dir/*.php") as $archivo) {
        $etiqueta = "$grupo/" . basename($archivo, '.php');
        if ($opciones['sin-bd'] && $grupo === 'integracion') {
            $lineas[] = "OMITIDA  $etiqueta (--sin-bd)";
            $omitidas++;
            continue;
        }
        $casos = (static fn (string $ruta): array => require $ruta)($archivo);
        foreach ($casos as $nombre => $prueba) {
            $total++;
            try {
                $prueba();
                $lineas[] = "OK       $etiqueta :: $nombre";
            } catch (Throwable $e) {
                $fallas++;
                $lineas[] = "FALLA    $etiqueta :: $nombre";
                $lineas[] = '         ' . $e::class . ': ' . $e->getMessage();
            }
        }
    }
}

$lineas[] = '';
$lineas[] = sprintf('Resumen: %d pruebas, %d correctas, %d fallas, %d archivos omitidos', $total, $total - $fallas, $fallas, $omitidas);
$texto = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $texto;

if ($opciones['evidencia'] !== null) {
    $ruta = $opciones['evidencia'];
    if (!is_dir(dirname($ruta))) {
        mkdir(dirname($ruta), 0777, true);
    }
    file_put_contents($ruta, $texto); // UTF-8, sin BOM (el ">" de PowerShell 5 guardaría UTF-16)
    echo "Evidencia escrita en $ruta" . PHP_EOL;
}
exit($fallas === 0 ? 0 : 1);
