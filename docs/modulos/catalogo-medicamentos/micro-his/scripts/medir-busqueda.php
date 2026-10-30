<?php
declare(strict_types=1);

/*
 * RNF-03 / métrica M-03: p95 de la búsqueda con 5 000 versiones en la base de PRUEBA.
 *   php scripts/medir-busqueda.php --evidencia=../semana-15/evidencia/rnf03-busqueda.txt
 * Carga datos ficticios en micro_medicamentos_test (la vacía antes) y mide 200 búsquedas.
 */

use Catalogo\Application\BuscarMedicamentosVigentes;
use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\Fecha;
use Catalogo\Domain\Modelo\FichaMedicamento;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Persistence\ConexionPdo;
use Catalogo\Persistence\DatosEjemplo;
use Catalogo\Persistence\GeneradorUuidV4;
use Catalogo\Persistence\RelojSistema;
use Catalogo\Persistence\RepositorioMedicamentosPdo;

require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';
$evidencia = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--evidencia=')) {
        $evidencia = substr($arg, 12);
    }
}

$pdo = ConexionPdo::crear($config['bd'], $config['bd']['base_prueba']);
$pdo->exec('TRUNCATE medicamentos, referencias_prescripcion');
$repo = new RepositorioMedicamentosPdo($pdo);
$ids = new GeneradorUuidV4();
DatosEjemplo::cargar($repo, $ids);

// Productos ficticios: principio y concentración distintos para no violar RN-04; mitad con 2 versiones.
$formas = ['TABLETA', 'CAPSULA', 'JARABE', 'SUSPENSION', 'CREMA'];
$inicio = microtime(true);
$versiones = 8;
for ($n = 1; $versiones < 5000; $n++) {
    [$ficha] = FichaMedicamento::validar([
        'nombre' => "Producto ficticio $n " . $formas[$n % 5], 'principio_activo' => 'Principio ficticio ' . intdiv($n, 7),
        'concentracion_valor' => (string) (10 + $n % 7), 'concentracion_unidad' => 'mg', 'forma_farmaceutica' => $formas[$n % 5],
        'via_administracion' => 'ORAL', 'presentacion' => 'Caja con 10 unidades',
    ]);
    $m = Medicamento::registrar($ids->nuevo(), DatosEjemplo::NORTE, CodigoMedicamento::desdeNumero(1000 + $n), $ficha, Fecha::desdeTexto('d', '2026-01-01'), 'carga.medicion');
    $repo->agregar($m);
    $versiones++;
    if ($n % 2 === 0 && $versiones < 5000) {
        [$cerrada, $nueva] = $m->nuevaVersion($ids->nuevo(), $ficha, Fecha::desdeTexto('d', '2026-06-01'), 'carga.medicion');
        $repo->reemplazarVigente($cerrada, $nueva);
        $versiones++;
    }
}
$carga = microtime(true) - $inicio;
$pdo->exec('ANALYZE medicamentos');

$buscar = new BuscarMedicamentosVigentes($repo, new RelojSistema());
$terminos = ['producto', 'ficticio 12', 'principio ficticio 3', 'MED-0012', 'amoxi', 'tableta', '% s', 'inexistente', 'paracetamol', 'crema'];
$tiempos = [];
for ($i = 0; $i < 200; $i++) {
    $filtros = ['q' => $terminos[$i % 10], 'fecha' => $i % 3 === 0 ? '2026-03-01' : ''];
    $t = hrtime(true);
    $buscar->ejecutar(DatosEjemplo::NORTE, $filtros);
    $tiempos[] = (hrtime(true) - $t) / 1e6;
}
sort($tiempos);
$p = fn (float $q) => $tiempos[(int) ceil($q * count($tiempos)) - 1];
$total = (int) $pdo->query('SELECT count(*) FROM medicamentos')->fetchColumn();

$lineas = [
    'RNF-03 / M-03 - p95 de la búsqueda (Micro-HIS, adaptador PDO, base de prueba)',
    'Fecha: ' . date('Y-m-d H:i:s T') . ' · PHP ' . PHP_VERSION . ' · ' . $pdo->query('SHOW server_version')->fetchColumn(),
    sprintf('Versiones en la tabla: %d (carga en %.1f s)', $total, $carga),
    sprintf('Búsquedas medidas: %d (10 términos, un tercio a fecha 2026-03-01)', count($tiempos)),
    sprintf('p50 = %.1f ms · p95 = %.1f ms · máximo = %.1f ms', $p(0.50), $p(0.95), end($tiempos)),
    sprintf('Umbral RNF-03: p95 <= 300 ms -> %s', $p(0.95) <= 300 ? 'CUMPLE' : 'NO CUMPLE'),
];
$texto = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $texto;
if ($evidencia !== null) {
    @mkdir(dirname($evidencia), 0777, true);
    file_put_contents($evidencia, $texto);
}
