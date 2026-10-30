<?php
declare(strict_types=1);

// Caso de uso con DOBLES de prueba (sin base de datos).

use Admision\Application\AdmitirPacienteYAsignarCama;
use Admision\Application\ConsultarCamasDisponibles;
use Admision\Application\Excepciones\AccesoDenegado;
use Admision\Domain\Excepciones\CamaNoEncontrada;
use Admision\Domain\Excepciones\CamaYaAsignada;
use Admision\Domain\Excepciones\ErrorPersistencia;
use Admision\Domain\Excepciones\PacienteConAdmisionActiva;
use Admision\Domain\Excepciones\PacienteNoEncontrado;
use Pruebas\Afirmar;
use Pruebas\Datos;
use Pruebas\Dobles\AdmisionRepositoryEspia;
use Pruebas\Dobles\CatalogoCamasFalso;
use Pruebas\Dobles\DirectorioPacientesFalso;
use Pruebas\Dobles\GeneradorIdSecuencial;
use Pruebas\Dobles\RelojFijo;

$casoDeUso = fn (AdmisionRepositoryEspia $repo) => new AdmitirPacienteYAsignarCama(
    new DirectorioPacientesFalso(), new CatalogoCamasFalso(), $repo, Datos::evaluador(), new RelojFijo(), new GeneradorIdSecuencial(),
);

return [
    'Camino feliz: PAC-00451 queda admitido en B-201 (CA-01)' => function () use ($casoDeUso) {
        $repo = new AdmisionRepositoryEspia();
        $r = $casoDeUso($repo)->ejecutar(Datos::solicitud(), Datos::contexto('usr-adm-01'));
        Afirmar::igual('ACTIVA', $r->estado);
        Afirmar::igual('B-201', $r->camaCodigo);
        Afirmar::igual('PAC-00451', $r->codigoPaciente);
        Afirmar::igual('2026-03-02T08:15:00+00:00', $r->creadaEn);
        Afirmar::igual(1, count($repo->registradas));
        Afirmar::igual(2, count($repo->registradas[0]->eventosPendientes()));
    },
    'Regla de dominio RN-02: segunda admisión activa rechazada sin registrar (CA-04)' => function () use ($casoDeUso) {
        $repo = new AdmisionRepositoryEspia([['HOSP-01', 'PAC-00452']]);
        Afirmar::lanza(PacienteConAdmisionActiva::class, fn () => $casoDeUso($repo)->ejecutar(Datos::solicitud('PAC-00452'), Datos::contexto('usr-adm-01')));
        Afirmar::igual([], $repo->registradas);
    },
    'Paciente inactivo PAC-00460 se rechaza (CA-03)' => function () use ($casoDeUso) {
        Afirmar::lanza(PacienteNoEncontrado::class, fn () => $casoDeUso(new AdmisionRepositoryEspia())->ejecutar(Datos::solicitud('PAC-00460'), Datos::contexto('usr-adm-01')));
    },
    'Rol MEDICO no puede admitir (RN-06, CA-08)' => function () use ($casoDeUso) {
        $repo = new AdmisionRepositoryEspia();
        Afirmar::lanza(AccesoDenegado::class, fn () => $casoDeUso($repo)->ejecutar(Datos::solicitud(), Datos::contexto('usr-med-01')));
        Afirmar::igual([], $repo->registradas);
    },
    'Usuario de HOSP-02 no alcanza camas de HOSP-01 (RN-04, CA-09)' => function () use ($casoDeUso) {
        Afirmar::lanza(CamaNoEncontrada::class, fn () => $casoDeUso(new AdmisionRepositoryEspia())->ejecutar(Datos::solicitud('PAC-00900', Datos::B201), Datos::contexto('usr-adm-02')));
    },
    'Error de persistencia (doble) se propaga y no devuelve resultado (CA-12)' => function () use ($casoDeUso) {
        $repo = new AdmisionRepositoryEspia();
        $repo->fallaAlRegistrar = new ErrorPersistencia('base de datos no disponible (simulado)');
        Afirmar::lanza(ErrorPersistencia::class, fn () => $casoDeUso($repo)->ejecutar(Datos::solicitud(), Datos::contexto('usr-adm-01')));
        Afirmar::igual([], $repo->registradas);
    },
    'Conflicto detectado al guardar (doble) llega como CamaYaAsignada (CA-07)' => function () use ($casoDeUso) {
        $repo = new AdmisionRepositoryEspia();
        $repo->fallaAlRegistrar = new CamaYaAsignada();
        Afirmar::lanza(CamaYaAsignada::class, fn () => $casoDeUso($repo)->ejecutar(Datos::solicitud(), Datos::contexto('usr-adm-01')));
    },
    'Camas disponibles: sin ocupadas y con aislamiento solo B-210 (CA-05, CA-06)' => function () {
        $consulta = new ConsultarCamasDisponibles(new CatalogoCamasFalso(), new AdmisionRepositoryEspia([], [Datos::B202]), Datos::evaluador());
        $codigos = fn (array $camas) => array_map(fn ($c) => $c->codigo, $camas);
        Afirmar::igual(['B-201'], $codigos($consulta->ejecutar('MEDICINA_INTERNA', 'GENERAL', false, Datos::contexto('usr-adm-01'))));
        Afirmar::igual(['B-210'], $codigos($consulta->ejecutar('MEDICINA_INTERNA', 'GENERAL', true, Datos::contexto('usr-sup-01'))));
    },
];
