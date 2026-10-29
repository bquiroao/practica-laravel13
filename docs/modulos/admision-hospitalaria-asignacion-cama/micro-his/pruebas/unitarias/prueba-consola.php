<?php
declare(strict_types=1);

// Presentación por consola con dobles: solo traduce argumentos y errores; no tiene reglas.

use Admision\Application\AdmitirPacienteYAsignarCama;
use Admision\Application\ConsultarCamasDisponibles;
use Admision\Domain\Excepciones\ErrorPersistencia;
use Admision\Presentation\Consola\AplicacionConsola;
use Admision\Presentation\Servicios;
use Admision\Presentation\UsuariosFicticios;
use Pruebas\Afirmar;
use Pruebas\Datos;
use Pruebas\Dobles\AdmisionRepositoryEspia;
use Pruebas\Dobles\CatalogoCamasFalso;
use Pruebas\Dobles\DirectorioPacientesFalso;
use Pruebas\Dobles\GeneradorIdSecuencial;
use Pruebas\Dobles\RelojFijo;

$correr = function (array $argv, ?AdmisionRepositoryEspia $repo = null): array {
    $repo ??= new AdmisionRepositoryEspia();
    $servicios = new Servicios(
        new AdmitirPacienteYAsignarCama(new DirectorioPacientesFalso(), new CatalogoCamasFalso(), $repo, Datos::evaluador(), new RelojFijo(), new GeneradorIdSecuencial()),
        new ConsultarCamasDisponibles(new CatalogoCamasFalso(), $repo, Datos::evaluador()),
    );
    $lineas = [];
    $consola = new AplicacionConsola($servicios, new UsuariosFicticios(Datos::crudos()['usuarios']), new GeneradorIdSecuencial(), function (string $l) use (&$lineas) {
        $lineas[] = $l;
    });
    return [$consola->ejecutar(array_merge(['consola.php'], $argv)), $lineas];
};
$admitir = ['admitir', '--usuario=usr-adm-01', '--paciente=PAC-00451', '--servicio=MEDICINA_INTERNA', '--tipo=GENERAL', '--origen=EMERGENCIA', '--cama=' . Datos::B201];

return [
    'Consola: admitir muestra la admisión ACTIVA con código y cama (CA-01)' => function () use ($correr, $admitir) {
        [$codigo, $lineas] = $correr($admitir);
        Afirmar::igual(0, $codigo);
        Afirmar::igual('Admisión ACTIVA: paciente PAC-00451 en cama B-201 (MEDICINA_INTERNA).', $lineas[0]);
    },
    'Consola: un MEDICO recibe ACCESO_DENEGADO y código de salida 1 (CA-08)' => function () use ($correr, $admitir) {
        $argv = $admitir;
        $argv[1] = '--usuario=usr-med-01';
        [$codigo, $lineas] = $correr($argv);
        Afirmar::igual([1, 'ERROR [ACCESO_DENEGADO] Su rol no permite esta operación.'], [$codigo, $lineas[0]]);
    },
    'Consola: error de persistencia sale con código 2 y pide reintentar (CA-12)' => function () use ($correr, $admitir) {
        $repo = new AdmisionRepositoryEspia();
        $repo->fallaAlRegistrar = new ErrorPersistencia('No se pudo registrar la admisión. No se guardó ningún dato.');
        [$codigo, $lineas] = $correr($admitir, $repo);
        Afirmar::igual(2, $codigo);
        Afirmar::verdadero(str_starts_with($lineas[0], 'ERROR [ERROR_PERSISTENCIA]'), $lineas[0]);
    },
    'Consola: sin camas compatibles muestra el estado vacío (E-05)' => function () use ($correr) {
        [$codigo, $lineas] = $correr(['camas', '--usuario=usr-adm-01', '--servicio=UCI', '--tipo=UCI', '--aislamiento']);
        Afirmar::igual([0, ['No hay camas disponibles compatibles con ese requerimiento.']], [$codigo, $lineas]);
    },
];
