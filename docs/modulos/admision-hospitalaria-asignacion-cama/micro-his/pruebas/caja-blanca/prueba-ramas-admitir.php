<?php
declare(strict_types=1);

// Semana 15 — CAJA BLANCA. Cubre cada decisión de AdmitirPacienteYAsignarCama::ejecutar() (D1..D9)
// y las 4 combinaciones de condiciones de CriterioAislamiento. Ver semana-15/README.md §3.

use Admision\Application\AdmitirPacienteYAsignarCama;
use Admision\Application\Excepciones\AccesoDenegado;
use Admision\Application\SolicitudAdmision;
use Admision\Domain\Elegibilidad\CriterioAislamiento;
use Admision\Domain\Excepciones\CamaNoElegible;
use Admision\Domain\Excepciones\CamaNoEncontrada;
use Admision\Domain\Excepciones\CamaYaAsignada;
use Admision\Domain\Excepciones\DatoInvalido;
use Admision\Domain\Excepciones\PacienteConAdmisionActiva;
use Admision\Domain\Excepciones\PacienteNoEncontrado;
use Admision\Domain\Modelo\Requerimiento;
use Pruebas\Afirmar;
use Pruebas\Datos;
use Pruebas\Dobles\AdmisionRepositoryEspia;
use Pruebas\Dobles\CatalogoCamasFalso;
use Pruebas\Dobles\DirectorioPacientesFalso;
use Pruebas\Dobles\GeneradorIdSecuencial;
use Pruebas\Dobles\RelojFijo;

$sol = fn (array $cambios = []) => new SolicitudAdmision(...array_merge([
    'codigoPaciente' => 'PAC-00451', 'servicio' => 'MEDICINA_INTERNA', 'tipoCama' => 'GENERAL',
    'requiereAislamiento' => false, 'origen' => 'EMERGENCIA', 'camaId' => Datos::B201,
], $cambios));
// Cada rama: [usuario, cambios en la solicitud, admisiones activas previas, camas ocupadas, excepción esperada]
$ramas = [
    'CB-01 D1 rol no permitido (MEDICO)' => ['usr-med-01', [], [], [], AccesoDenegado::class],
    'CB-02 D2 formato de código inválido' => ['usr-adm-01', ['codigoPaciente' => 'PAC-1'], [], [], DatoInvalido::class],
    'CB-03 D3 requerimiento inválido (tipo de cama)' => ['usr-adm-01', ['tipoCama' => 'CAMILLA'], [], [], DatoInvalido::class],
    'CB-04 D4 origen inválido' => ['usr-adm-01', ['origen' => 'OTRO'], [], [], DatoInvalido::class],
    'CB-05 D5 paciente inexistente o inactivo' => ['usr-adm-01', ['codigoPaciente' => 'PAC-00460'], [], [], PacienteNoEncontrado::class],
    'CB-06 D6 paciente con admisión activa' => ['usr-adm-01', [], [['HOSP-01', 'PAC-00451']], [], PacienteConAdmisionActiva::class],
    'CB-07 D7 cama inexistente en el hospital' => ['usr-adm-01', ['camaId' => Datos::B201_HOSP02], [], [], CamaNoEncontrada::class],
    'CB-08 D8 cama ya ocupada (aviso temprano, no llega a registrar)' => ['usr-adm-01', [], [], [Datos::B201], CamaYaAsignada::class],
    'CB-09 D9 cama no elegible (aislamiento)' => ['usr-adm-01', ['requiereAislamiento' => true], [], [], CamaNoElegible::class],
];

$pruebas = [];
foreach ($ramas as $nombre => [$usuario, $cambios, $activas, $ocupadas, $esperada]) {
    $pruebas[$nombre] = function () use ($sol, $usuario, $cambios, $activas, $ocupadas, $esperada) {
        $repo = new AdmisionRepositoryEspia($activas, $ocupadas);
        $uc = new AdmitirPacienteYAsignarCama(new DirectorioPacientesFalso(), new CatalogoCamasFalso(), $repo, Datos::evaluador(), new RelojFijo(), new GeneradorIdSecuencial());
        Afirmar::lanza($esperada, fn () => $uc->ejecutar($sol($cambios), Datos::contexto($usuario)));
        Afirmar::igual([], $repo->registradas, 'ninguna rama de error llega a registrar');
    };
}
$pruebas['CB-10 todas las decisiones en el sentido feliz, con SUPERVISOR_CAMAS (segunda condición de D1)'] = function () use ($sol) {
    $repo = new AdmisionRepositoryEspia();
    $uc = new AdmitirPacienteYAsignarCama(new DirectorioPacientesFalso(), new CatalogoCamasFalso(), $repo, Datos::evaluador(), new RelojFijo(), new GeneradorIdSecuencial());
    Afirmar::igual('ACTIVA', $uc->ejecutar($sol(), Datos::contexto('usr-sup-01'))->estado);
    Afirmar::igual(1, count($repo->registradas));
};
// Condiciones de CriterioAislamiento: (requiere, cama de aislamiento) en sus 4 combinaciones.
foreach ([[false, false, null], [true, true, null], [true, false, 'El paciente requiere una cama de aislamiento.'],
    [false, true, 'La cama de aislamiento se reserva para quien la requiere.']] as $i => [$requiere, $camaAisl, $motivo]) {
    $pruebas[sprintf('CB-%02d CriterioAislamiento requiere=%s cama=%s', 11 + $i, $requiere ? 'sí' : 'no', $camaAisl ? 'aislamiento' : 'general')] =
        function () use ($requiere, $camaAisl, $motivo) {
            $cama = Datos::cama($camaAisl ? 'B-210' : 'B-201');
            Afirmar::igual($motivo, (new CriterioAislamiento())->motivoDeRechazo($cama, Requerimiento::desde('MEDICINA_INTERNA', 'GENERAL', $requiere)));
        };
}
return $pruebas;
