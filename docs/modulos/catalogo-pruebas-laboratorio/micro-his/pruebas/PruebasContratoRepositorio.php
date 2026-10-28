<?php
declare(strict_types=1);

// El mismo contrato se verifica contra los dos adaptadores (sustituibles entre sí).
use Lab\Application\RegistrarPrueba;
use Lab\Domain\LabTestRepository;
use Lab\Persistence\Conexion;
use Lab\Persistence\InMemoryLabTestRepository;
use Lab\Persistence\PdoLabTestRepository;

$adaptadores = [
    'InMemory' => static fn (): LabTestRepository => new InMemoryLabTestRepository(),
    'PDO' => static function (): LabTestRepository {
        $config = require __DIR__ . '/../config.php';
        $pdo = Conexion::crear(['base' => $config['base_pruebas']] + $config);
        crearEsquema($pdo);
        return new PdoLabTestRepository($pdo);
    },
];

$pruebas = [];
foreach ($adaptadores as $nombre => $crear) {
    $pruebas["T-09 Contrato $nombre: versiones, filtros y vigencia se comportan igual"] = function () use ($crear): void {
        $repo = $crear();
        $alta = new RegistrarPrueba($repo, new RelojFijo());
        $alta->ejecutar(datos('hem-001'), 'HOSP-01', 'jefe.lab');
        $alta->ejecutar(datos('glu-001-v1'), 'HOSP-01', 'jefe.lab');
        $alta->ejecutar(datos('glu-001-v2'), 'HOSP-01', 'jefe.lab');
        $alta->ejecutar(datos('hem-001'), 'HOSP-02', 'jefe.lab');                       // otro hospital
        $enero = new DateTimeImmutable('2027-01-15');
        afirmar($repo->ultimaVersion('HOSP-01', 'GLU-001')?->version === 2, 'La última versión de GLU-001 es la 2');
        afirmar($repo->buscarVigentePorCodigo('HOSP-01', 'GLU-001', $enero)?->version === 1, 'En enero rige la versión 1');
        afirmar(count($repo->buscarVigentes('HOSP-01', $enero)) === 2, 'HOSP-01 tiene 2 pruebas vigentes en enero');
        afirmar(count($repo->buscarVigentes('HOSP-01', $enero, 'gluc')) === 1, 'El filtro de texto encuentra GLU-001');
        afirmar(count($repo->buscarVigentes('HOSP-01', $enero, '', 'hematologia', 'sangre-total')) === 1, 'Filtro por sección y muestra');
        afirmar($repo->buscarVigentes('HOSP-01', new DateTimeImmutable('2027-01-03')) === [], 'Antes del 2027-01-04 no hay vigentes');
    };
}
return $pruebas;
