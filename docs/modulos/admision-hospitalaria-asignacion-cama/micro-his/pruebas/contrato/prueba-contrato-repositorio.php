<?php
declare(strict_types=1);

// Prueba de CONTRATO: las mismas verificaciones corren contra el adaptador InMemory y el adaptador PDO.
// Si un adaptador pasa estas pruebas, el caso de uso puede usarlo sin cambiar.

use Admision\Domain\Excepciones\CamaYaAsignada;
use Admision\Domain\Excepciones\PacienteConAdmisionActiva;
use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\TenantId;
use Admision\Persistence\InMemory\InMemoryAdmisionRepository;
use Admision\Persistence\InMemory\InMemoryCatalogoCamas;
use Admision\Persistence\InMemory\InMemoryDirectorioPacientes;
use Admision\Persistence\Pdo\PdoAdmisionRepository;
use Admision\Persistence\Pdo\PdoCatalogoCamas;
use Admision\Persistence\Pdo\PdoDirectorioPacientes;
use Pruebas\Afirmar;
use Pruebas\BaseDePrueba;
use Pruebas\Datos;

$adaptadores = [
    'InMemory' => fn () => [
        new InMemoryAdmisionRepository(),
        new InMemoryCatalogoCamas(Datos::crudos()['camas']),
        new InMemoryDirectorioPacientes(Datos::crudos()['pacientes']),
    ],
    'PDO' => function () {
        $pdo = BaseDePrueba::conexion();
        BaseDePrueba::limpiar($pdo);
        return [new PdoAdmisionRepository($pdo), new PdoCatalogoCamas($pdo), new PdoDirectorioPacientes($pdo)];
    },
];
$h1 = TenantId::desde('HOSP-01');
$h2 = TenantId::desde('HOSP-02');
$id1 = 'a0000000-0000-4000-8000-000000000001';
$id2 = 'a0000000-0000-4000-8000-000000000002';

$casos = [
    'registra y relee admisión, eventos y cama ocupada' => function ($repo) use ($h1, $id1) {
        $repo->registrar(Datos::admision($id1, 'PAC-00451', Datos::cama('B-201')));
        $a = $repo->buscarPorId($h1, $id1);
        Afirmar::igual(['ACTIVA', 'B-201', 'PAC-00451'], [$a?->estado->value, $a?->camaCodigo, $a?->codigoPaciente->valor]);
        Afirmar::igual([], $a->eventosPendientes());
        Afirmar::igual(['ADMISION_CREADA', 'CAMA_ASIGNADA'], array_map(fn ($e) => $e->tipo->value, $repo->eventos($h1, $id1)));
        Afirmar::igual([Datos::B201], $repo->camasOcupadas($h1));
        Afirmar::verdadero($repo->tieneAdmisionActiva($h1, CodigoPaciente::desde('PAC-00451')), 'admisión activa');
    },
    'RN-01: la misma cama no se asigna dos veces y no queda nada de la segunda' => function ($repo) use ($h1, $id1, $id2) {
        $repo->registrar(Datos::admision($id1, 'PAC-00451', Datos::cama('B-201')));
        Afirmar::lanza(CamaYaAsignada::class, fn () => $repo->registrar(Datos::admision($id2, 'PAC-00452', Datos::cama('B-201'))));
        Afirmar::igual(null, $repo->buscarPorId($h1, $id2));
        Afirmar::verdadero(!$repo->tieneAdmisionActiva($h1, CodigoPaciente::desde('PAC-00452')), 'PAC-00452 sin admisión');
    },
    'RN-02: el mismo paciente no tiene dos admisiones activas' => function ($repo) use ($h1, $id1, $id2) {
        $repo->registrar(Datos::admision($id1, 'PAC-00451', Datos::cama('B-201')));
        Afirmar::lanza(PacienteConAdmisionActiva::class, fn () => $repo->registrar(Datos::admision($id2, 'PAC-00451', Datos::cama('B-202'))));
        Afirmar::igual([Datos::B201], $repo->camasOcupadas($h1));
    },
    'D-04: mismo paciente y misma cama a la vez responden igual (RN-02 primero)' => function ($repo) use ($id1, $id2) {
        $repo->registrar(Datos::admision($id1, 'PAC-00451', Datos::cama('B-201')));
        Afirmar::lanza(PacienteConAdmisionActiva::class, fn () => $repo->registrar(Datos::admision($id2, 'PAC-00451', Datos::cama('B-201'))));
    },
    'otro hospital no ve la admisión; un id mal formado no existe' => function ($repo) use ($h1, $h2, $id1) {
        $repo->registrar(Datos::admision($id1, 'PAC-00451', Datos::cama('B-201')));
        Afirmar::igual([null, [], []], [$repo->buscarPorId($h2, $id1), $repo->eventos($h2, $id1), $repo->camasOcupadas($h2)]);
        Afirmar::igual([null, []], [$repo->buscarPorId($h1, 'no-es-uuid'), $repo->eventos($h1, 'no-es-uuid')]);
    },
    'catálogo y directorio responden igual' => function ($repo, $catalogo, $directorio) use ($h1, $h2) {
        Afirmar::igual(['B-201', 'B-202', 'B-203', 'B-210', 'C-101', 'U-01'], array_map(fn ($c) => $c->codigo, $catalogo->listar($h1)));
        Afirmar::igual(null, $catalogo->buscar($h2, Datos::B201));
        Afirmar::igual(null, $catalogo->buscar($h1, 'no-es-uuid'));
        Afirmar::igual([true, false], [
            $directorio->existeActivo($h1, CodigoPaciente::desde('PAC-00451')),
            $directorio->existeActivo($h1, CodigoPaciente::desde('PAC-00460')),
        ]);
    },
];

$pruebas = [];
foreach ($casos as $nombre => $caso) {
    foreach ($adaptadores as $adaptador => $fabrica) {
        $pruebas["[{$adaptador}] {$nombre}"] = fn () => $caso(...$fabrica());
    }
}
return $pruebas;
