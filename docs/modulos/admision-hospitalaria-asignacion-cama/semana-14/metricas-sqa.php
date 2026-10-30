<?php
declare(strict_types=1);

// Mide las métricas M-01, M-03, M-04, M-05 y M-07 del plan SQA.
// Uso (desde semana-14/):
//   php metricas-sqa.php [--base=micro_admision] [--registro=ruta/al/registro-del-servidor.log]
// El registro es la salida del servidor (php -S …): líneas JSON que escribe ApiKernel.

require __DIR__ . '/../micro-his/autoload.php';

use Admision\Persistence\Pdo\ConexionPdo;

$base = 'micro_admision';
$registro = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(base|registro)=(.+)$/', $arg, $m) === 1) {
        ${$m[1]} = $m[2];
    }
}
$consultar = function (PDO $pdo, string $sql): array {
    $s = $pdo->prepare($sql);
    $s->execute();
    return $s->fetchAll();
};
$informe = function (string $id, string $nombre, string $valor, string $umbral, bool $cumple): void {
    $nombre .= str_repeat(' ', max(0, 44 - mb_strlen($nombre)));
    printf("%s  %s %-14s umbral %-10s %s\n", $id, $nombre, $valor, $umbral, $cumple ? 'CUMPLE' : 'NO CUMPLE');
};

try {
    $pdo = ConexionPdo::desdeEntorno($base);
    $dobles = $consultar($pdo, 'SELECT tenant_id, cama_id, count(*) AS n FROM asignacion_cama
        WHERE liberada_en IS NULL GROUP BY tenant_id, cama_id HAVING count(*) > 1');
    $informe('M-01', 'Camas con más de una asignación activa', (string) count($dobles), '= 0', $dobles === []);
    $incompletas = $consultar($pdo, "SELECT a.id FROM admision a LEFT JOIN evento_admision e ON e.admision_id = a.id
        GROUP BY a.id HAVING count(*) FILTER (WHERE e.tipo IN ('ADMISION_CREADA', 'CAMA_ASIGNADA')) < 2");
    $total = (int) ($consultar($pdo, 'SELECT count(*) AS n FROM admision')[0]['n']);
    $informe('M-07', 'Admisiones con sus 2 eventos', ($total - count($incompletas)) . " de {$total}", '= 100 %', $incompletas === []);
} catch (PDOException $e) {
    echo "Sin conexión a la base {$base}: no se midieron M-01 ni M-07." . PHP_EOL;
}

if ($registro !== null && is_file($registro)) {
    $lineas = (array) file($registro, FILE_IGNORE_NEW_LINES);
    $sol = [];
    foreach ($lineas as $l) {
        if (preg_match('/(\{"request_id".*\})/', $l, $m) === 1) {
            $sol[] = json_decode($m[1], true);
        }
    }
    $post = array_values(array_filter($sol, fn ($s) => $s['metodo'] === 'POST' && $s['ruta'] === '/api/v1/admisiones'));
    $duraciones = array_map(fn ($s) => $s['duracion_ms'], $post);
    sort($duraciones);
    $p95 = $duraciones === [] ? 0 : $duraciones[(int) ceil(0.95 * count($duraciones)) - 1];
    $informe('M-03', 'p95 de POST /admisiones (ms)', "{$p95} (n=" . count($post) . ')', '<= 800', $p95 <= 800);
    $conflictos = count(array_filter($post, fn ($s) => $s['estado'] === 409));
    $tasa = $post === [] ? 0.0 : 100 * $conflictos / count($post);
    $informe('M-04', 'POST con 409 (%)', sprintf('%.1f', $tasa), '<= 5 %', $tasa <= 5);
    $fugas = count(array_filter($lineas, fn ($l) => str_contains($l, 'PAC-')));
    $informe('M-05', 'Líneas del registro con código de paciente', (string) $fugas, '= 0', $fugas === 0);
} else {
    echo 'Sin --registro: no se midieron M-03, M-04 ni M-05.' . PHP_EOL;
}
