<?php
declare(strict_types=1);

use Admision\Application\ConsultarAdmision;
use Admision\Application\ConsultarTrazabilidad;
use Admision\Application\Excepciones\AccesoDenegado;
use Admision\Domain\Excepciones\AdmisionNoEncontrada;
use Admision\Persistence\InMemory\InMemoryAdmisionRepository;
use Pruebas\Afirmar;
use Pruebas\Datos;

$repoConUna = function (): InMemoryAdmisionRepository {
    $repo = new InMemoryAdmisionRepository();
    $repo->registrar(Datos::admision('a0000000-0000-4000-8000-000000000001', 'PAC-00451', Datos::cama('B-201')));
    return $repo;
};
$id = 'a0000000-0000-4000-8000-000000000001';

return [
    'El médico consulta la admisión: código y cama, sin nombres (CA-10)' => function () use ($repoConUna, $id) {
        $r = (new ConsultarAdmision($repoConUna()))->ejecutar($id, Datos::contexto('usr-med-01'));
        Afirmar::igual(['PAC-00451', 'B-201', 'ACTIVA'], [$r->codigoPaciente, $r->camaCodigo, $r->estado]);
    },
    'Otro hospital no encuentra la admisión (RN-04, CA-09)' => function () use ($repoConUna, $id) {
        Afirmar::lanza(AdmisionNoEncontrada::class, fn () => (new ConsultarAdmision($repoConUna()))->ejecutar($id, Datos::contexto('usr-adm-02')));
    },
    'Trazabilidad: el supervisor ve los eventos en orden; el médico no (CA-11)' => function () use ($repoConUna, $id) {
        $repo = $repoConUna();
        $eventos = (new ConsultarTrazabilidad($repo))->ejecutar($id, Datos::contexto('usr-sup-01'));
        Afirmar::igual(['ADMISION_CREADA', 'CAMA_ASIGNADA'], array_column($eventos, 'tipo'));
        Afirmar::igual('sol-prueba-0001', $eventos[0]['solicitud_id']);
        Afirmar::lanza(AccesoDenegado::class, fn () => (new ConsultarTrazabilidad($repo))->ejecutar($id, Datos::contexto('usr-med-01')));
    },
];
