<?php
declare(strict_types=1);

// Semana 5: mide la consulta de la alerta en proceso y por HTTP para decidir la frontera.
// Uso: php scripts/medir-frontera.php [--n=200] [--url=http://127.0.0.1:8042]
require __DIR__ . '/../bootstrap.php';

$op = getopt('', ['n:', 'url:']);
$n = (int) ($op['n'] ?? 200);

function percentiles(array $ms): string
{
    sort($ms);
    $p = fn (float $q) => $ms[(int) floor(($q / 100) * (count($ms) - 1))];
    return sprintf('p50 %.2f ms | p95 %.2f ms | máx %.2f ms', $p(50), $p(95), max($ms));
}

$consultar = armarCasosDeUso()['consultar'];
$enProceso = [];
for ($i = 0; $i < $n; $i++) {
    $t = hrtime(true);
    $consultar->ejecutar('PAC-00451');
    $enProceso[] = (hrtime(true) - $t) / 1e6;
}
echo "En proceso (PDO, {$n} consultas): " . percentiles($enProceso) . PHP_EOL;

if (isset($op['url'])) {
    $contexto = stream_context_create(['http' => ['header' => "Authorization: Bearer tok-enf-h01\r\n", 'ignore_errors' => true]]);
    $porHttp = [];
    $fallos = 0;
    for ($i = 0; $i < $n; $i++) {
        $t = hrtime(true);
        $cuerpo = @file_get_contents($op['url'] . '/api/v1/pacientes/PAC-00451/alerta', false, $contexto);
        $porHttp[] = (hrtime(true) - $t) / 1e6;
        $fallos += $cuerpo === false || !str_contains($cuerpo, '"nivel"') ? 1 : 0;
    }
    echo "Por HTTP ({$n} peticiones, {$fallos} fallidas): " . percentiles($porHttp) . PHP_EOL;
}
