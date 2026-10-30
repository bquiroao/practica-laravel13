<?php
declare(strict_types=1);

/*
 * Ejecutor de pruebas propio (sin PHPUnit).
 *   php pruebas/ejecutar.php [--evidencia=ruta.txt] [--sin-bd] [--grupo=unitarias]
 * Cada archivo de pruebas devuelve un arreglo nombre => función y se carga en su propio ámbito.
 * Los archivos de pruebas/integracion usan la base de prueba (config.php); --sin-bd los omite.
 * Una prueba cuyo nombre lleva [DEFECTO D-NN] documenta un defecto abierto (semana 15): si falla se
 * informa como DEFECTO y no rompe la ejecución; si pasa, se avisa para cerrar el defecto.
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
        putenv('CATALOGO_SIN_BD=1'); // la prueba de contrato corre entonces solo contra InMemory
    }
}

$grupos = array_values(array_filter(glob(__DIR__ . '/*', GLOB_ONLYDIR), fn ($d) => basename($d) !== 'dobles'));
$lineas = ['Pruebas del Micro-HIS Catálogo de medicamentos', 'Fecha: ' . date('Y-m-d H:i:s T') . ' · PHP ' . PHP_VERSION, ''];
$total = $fallas = $omitidas = $defectos = 0;

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
            $esDefecto = preg_match('/\[DEFECTO D-\d{2}\]/', $nombre) === 1;
            try {
                $prueba();
                $lineas[] = str_pad($esDefecto ? 'CORREGIDO' : 'OK', 9) . "$etiqueta :: $nombre" . ($esDefecto ? ' (ya pasa: cerrar el defecto)' : '');
            } catch (Throwable $e) {
                $esDefecto ? $defectos++ : $fallas++;
                $lineas[] = str_pad($esDefecto ? 'DEFECTO' : 'FALLA', 9) . "$etiqueta :: $nombre";
                $lineas[] = '         ' . $e::class . ': ' . $e->getMessage();
            }
        }
    }
}

$lineas[] = '';
$resumen = sprintf('Resumen: %d pruebas, %d correctas, %d fallas, %d archivos omitidos', $total, $total - $fallas - $defectos, $fallas, $omitidas);
$lineas[] = $defectos > 0 ? "$resumen, $defectos defectos abiertos documentados" : $resumen;
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
