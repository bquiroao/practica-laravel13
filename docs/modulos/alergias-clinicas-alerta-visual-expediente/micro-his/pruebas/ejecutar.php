<?php
declare(strict_types=1);

// Uso: php pruebas/ejecutar.php [--solo=semana03] [--evidencia=../semana-03/evidencia/salida-pruebas.txt]
require __DIR__ . '/../autoload.php';
require __DIR__ . '/Arnes.php';

$opciones = getopt('', ['solo:', 'evidencia:']);
$solo = $opciones['solo'] ?? '';
$archivos = glob(__DIR__ . '/semana*.php');
sort($archivos);

/** Cada archivo se carga en su propio ámbito: sus variables no chocan. */
function cargarPruebas(string $archivo): array
{
    return require $archivo;
}

$lineas = [];
$cuenta = ['correctas' => 0, 'fallidas' => 0, 'omitidas' => 0, 'defectos' => 0];
foreach ($archivos as $archivo) {
    $nombre = basename($archivo, '.php');
    if ($solo !== '' && !str_starts_with($nombre, $solo)) {
        continue;
    }
    $lineas[] = "== {$nombre}";
    foreach (cargarPruebas($archivo) as $titulo => $prueba) {
        $esDefecto = str_starts_with($titulo, '[DEFECTO');
        try {
            $prueba();
            if ($esDefecto) {
                $lineas[] = "[AVISO] {$titulo}: ya no se reproduce; revise el registro de defectos";
                $cuenta['correctas']++;
            } else {
                $lineas[] = "[OK] {$titulo}";
                $cuenta['correctas']++;
            }
        } catch (Omitida $e) {
            $lineas[] = "[OMITIDA] {$titulo}: {$e->getMessage()}";
            $cuenta['omitidas']++;
        } catch (Throwable $e) {
            $tipo = $esDefecto ? 'defectos' : 'fallidas';
            $lineas[] = ($esDefecto ? '[DEFECTO] ' : '[FALLA] ') . "{$titulo}: {$e->getMessage()}";
            $cuenta[$tipo]++;
        }
    }
}
$total = array_sum($cuenta);
$lineas[] = sprintf(
    'RESULTADO: %d pruebas, %d correctas, %d fallidas, %d omitidas, %d con defecto registrado',
    $total, $cuenta['correctas'], $cuenta['fallidas'], $cuenta['omitidas'], $cuenta['defectos'],
);
$texto = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $texto;

if (isset($opciones['evidencia'])) {
    $cabecera = 'Fecha: ' . date('Y-m-d H:i:s') . ' | PHP ' . PHP_VERSION . ' | ' . implode(' ', array_slice($argv, 1)) . PHP_EOL;
    @mkdir(dirname($opciones['evidencia']), 0777, true);
    file_put_contents($opciones['evidencia'], $cabecera . $texto); // UTF-8, no UTF-16
}
exit($cuenta['fallidas'] > 0 ? 1 : 0);
