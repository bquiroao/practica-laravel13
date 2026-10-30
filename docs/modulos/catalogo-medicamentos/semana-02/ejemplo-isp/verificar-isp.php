<?php
declare(strict_types=1);

// Mide, con Reflection, cuántos métodos ve cada cliente y cuántos usa de verdad.
// Uso: php verificar-isp.php [--evidencia=../evidencia/verificacion-isp.txt]

require __DIR__ . '/antes.php';
require __DIR__ . '/despues.php';

const AJENOS = ['actualizarPrecio', 'existencias'];
const CLIENTES = ['BuscarMedicamentosVigentes', 'ConsultarHistorial', 'RegistrarMedicamento', 'EliminarRegistroSinUso'];

function medir(string $clase): array
{
    $ref = new ReflectionClass($clase);
    $visibles = [];
    foreach ($ref->getConstructor()->getParameters() as $p) {
        $tipo = new ReflectionClass((string) $p->getType());
        foreach ($tipo->getMethods() as $m) {
            $visibles[$m->getName()] = true;
        }
    }
    $lineas = file($ref->getFileName());
    $cuerpo = implode('', array_slice($lineas, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
    preg_match_all('/\$this->\w+->(\w+)\(/', $cuerpo, $m);
    $usados = array_values(array_unique($m[1]));
    $noUsados = array_values(array_diff(array_keys($visibles), $usados));
    return [
        'visibles' => count($visibles),
        'usados' => count($usados),
        'no_usados' => count($noUsados),
        'ajenos' => count(array_intersect(array_keys($visibles), AJENOS)),
        'lista_no_usados' => $noUsados,
    ];
}

$salida = [];
$salida[] = 'Verificación ISP - Catálogo de medicamentos (semana 2)';
$salida[] = 'PHP ' . PHP_VERSION;
$salida[] = '';
$salida[] = sprintf('%-28s %-8s %8s %6s %9s %7s', 'Cliente', 'Diseño', 'Visibles', 'Usados', 'No usados', 'Ajenos');
$totales = ['IspAntes' => [0, 0], 'IspDespues' => [0, 0]];
foreach (CLIENTES as $cliente) {
    foreach (['IspAntes' => 'antes', 'IspDespues' => 'después'] as $ns => $etiqueta) {
        $r = medir("$ns\\$cliente");
        $totales[$ns][0] += $r['no_usados'];
        $totales[$ns][1] += $r['ajenos'];
        $salida[] = sprintf('%-28s %-8s %8d %6d %9d %7d', $cliente, $etiqueta, $r['visibles'], $r['usados'], $r['no_usados'], $r['ajenos']);
        if ($ns === 'IspDespues' && $r['no_usados'] > 0) {
            $salida[] = '    no usados después: ' . implode(', ', $r['lista_no_usados']);
        }
    }
}
$salida[] = '';
$salida[] = sprintf('Total de métodos visibles no usados: antes %d, después %d', $totales['IspAntes'][0], $totales['IspDespues'][0]);
$salida[] = sprintf('Métodos ajenos al catálogo visibles: antes %d, después %d', $totales['IspAntes'][1], $totales['IspDespues'][1]);
$dobleAntes = count((new ReflectionClass(IspAntes\RepositorioCatalogo::class))->getMethods());
$dobleDespues = count((new ReflectionClass(IspDespues\BusquedaCatalogo::class))->getMethods());
$salida[] = sprintf('Métodos que debe implementar un doble para probar la búsqueda: antes %d, después %d', $dobleAntes, $dobleDespues);

$cumple = $totales['IspDespues'][0] < $totales['IspAntes'][0] && $totales['IspDespues'][1] === 0;
$salida[] = 'Resultado: ' . ($cumple ? 'CUMPLE (menos métodos no usados y ninguno ajeno)' : 'NO CUMPLE');

$texto = implode(PHP_EOL, $salida) . PHP_EOL;
echo $texto;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--evidencia=')) {
        $ruta = substr($arg, strlen('--evidencia='));
        if (!is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0777, true);
        }
        file_put_contents($ruta, $texto);
        echo "Evidencia escrita en $ruta (UTF-8)" . PHP_EOL;
    }
}
exit($cumple ? 0 : 1);
