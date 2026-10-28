<?php
declare(strict_types=1);

// Uso (desde micro-his/): php pruebas/ejecutar.php [--evidencia=../semana-0N/evidencia/salida-pruebas.txt]
// El archivo de evidencia lo escribe este script en UTF-8 (no usar ">" en PowerShell).
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/soporte.php';

$evidencia = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--evidencia=')) {
        $evidencia = substr($arg, strlen('--evidencia='));
    }
}

// Cada archivo se carga en su propio ámbito para que sus variables no choquen.
$cargar = static fn (string $archivo): array => require $archivo;
$pruebas = [];
foreach (glob(__DIR__ . '/Pruebas*.php') as $archivo) {
    $pruebas += $cargar($archivo);
}
ksort($pruebas, SORT_NATURAL);

$salida = ['Micro-HIS Catálogo de pruebas de laboratorio — ' . date('Y-m-d H:i:s') . ' — PHP ' . PHP_VERSION, ''];
$fallidas = 0;
foreach ($pruebas as $nombre => $prueba) {
    try {
        $prueba();
        $salida[] = "[OK]    $nombre";
    } catch (Throwable $e) {
        $fallidas++;
        $salida[] = "[FALLA] $nombre" . PHP_EOL . '        ' . get_class($e) . ': ' . $e->getMessage();
    }
}
$salida[] = '';
$salida[] = sprintf('Resultado: %d pruebas, %d correctas, %d fallidas', count($pruebas), count($pruebas) - $fallidas, $fallidas);

$texto = implode(PHP_EOL, $salida) . PHP_EOL;
echo $texto;
if ($evidencia !== null) {
    if (!is_dir(dirname($evidencia))) {
        mkdir(dirname($evidencia), 0777, true);
    }
    file_put_contents($evidencia, $texto);
    echo "Evidencia guardada en $evidencia" . PHP_EOL;
}
exit($fallidas > 0 ? 1 : 0);
