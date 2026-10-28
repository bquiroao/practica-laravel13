<?php
declare(strict_types=1);

// Adaptador PDO contra la base de prueba micro_lab_test (Docker, puerto 55441).
use Lab\Application\ConsultarPruebasVigentes;
use Lab\Application\RegistrarPrueba;
use Lab\Domain\ErrorPersistencia;
use Lab\Persistence\Conexion;
use Lab\Persistence\PdoLabTestRepository;

$conectar = static function (): PDO {
    $config = require __DIR__ . '/../config.php';
    $pdo = Conexion::crear(['base' => $config['base_pruebas']] + $config);
    crearEsquema($pdo); // tabla limpia en cada prueba
    return $pdo;
};

return [
    'T-07 PDO: guarda dos pruebas y consulta solo las vigentes en la fecha (RN-06)' => function () use ($conectar): void {
        $repo = new PdoLabTestRepository($conectar());
        $alta = new RegistrarPrueba($repo, new RelojFijo());
        $alta->ejecutar(datos('hem-001'), 'HOSP-01', 'jefe.lab');
        $alta->ejecutar(datos('glu-001-v1', ['vigente_desde' => '2027-03-01']), 'HOSP-01', 'jefe.lab');
        $vigentes = (new ConsultarPruebasVigentes($repo, new RelojFijo()))->ejecutar('HOSP-01', ['fecha' => '2027-01-15']);
        afirmar(count($vigentes) === 1 && $vigentes[0]->codigo === 'HEM-001', 'Solo HEM-001 está vigente el 2027-01-15');
    },
    'T-08 PDO: si el INSERT falla, el cierre de la versión anterior se revierte (RN-07)' => function () use ($conectar): void {
        $pdo = $conectar();
        $repo = new PdoLabTestRepository($pdo);
        $v1 = (new RegistrarPrueba($repo, new RelojFijo()))->ejecutar(datos('glu-001-v1'), 'HOSP-01', 'jefe.lab');
        // Se fuerza la falla: otra fila con la misma versión viola uq_lab_test_version.
        esperarExcepcion(ErrorPersistencia::class, fn () => $repo->guardarNuevaVersion($v1, $v1->cerrarAntesDe(new DateTimeImmutable('2027-02-01'))));
        $st = $pdo->prepare('SELECT valid_to FROM lab_test WHERE id = :id');
        $st->execute([':id' => $v1->id]);
        afirmar($st->fetchColumn() === null, 'valid_to de la versión 1 debe seguir vacío tras el ROLLBACK');
    },
];
