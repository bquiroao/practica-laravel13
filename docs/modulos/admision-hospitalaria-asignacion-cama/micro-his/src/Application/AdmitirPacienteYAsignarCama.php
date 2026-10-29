<?php
declare(strict_types=1);

namespace Admision\Application;

use Admision\Application\Puertos\GeneradorId;
use Admision\Application\Puertos\Reloj;
use Admision\Domain\Elegibilidad\EvaluadorElegibilidad;
use Admision\Domain\Excepciones\CamaNoEncontrada;
use Admision\Domain\Excepciones\CamaYaAsignada;
use Admision\Domain\Excepciones\DatoInvalido;
use Admision\Domain\Excepciones\PacienteConAdmisionActiva;
use Admision\Domain\Excepciones\PacienteNoEncontrado;
use Admision\Domain\Modelo\Admision;
use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\OrigenAdmision;
use Admision\Domain\Modelo\Requerimiento;
use Admision\Domain\Modelo\Rol;
use Admision\Domain\Repositorio\AdmisionRepository;
use Admision\Domain\Repositorio\CatalogoCamas;
use Admision\Domain\Repositorio\DirectorioPacientes;

/** CU-01. Los comentarios A-NN / E-NN apuntan al diagrama de actividad de la semana 1. */
final class AdmitirPacienteYAsignarCama
{
    public function __construct(
        private readonly DirectorioPacientes $pacientes,
        private readonly CatalogoCamas $catalogo,
        private readonly AdmisionRepository $admisiones,
        private readonly EvaluadorElegibilidad $evaluador,
        private readonly Reloj $reloj,
        private readonly GeneradorId $ids,
    ) {
    }

    public function ejecutar(SolicitudAdmision $s, ContextoUsuario $u): ResultadoAdmision
    {
        $u->exigirRol(Rol::ADMISIONISTA, Rol::SUPERVISOR_CAMAS);                 // A-02 / E-01, RN-06
        $paciente = CodigoPaciente::desde($s->codigoPaciente);                   // A-03 / E-02
        $requerimiento = Requerimiento::desde($s->servicio, $s->tipoCama, $s->requiereAislamiento);
        $origen = OrigenAdmision::tryFrom($s->origen) ?? throw new DatoInvalido('Origen de admisión inválido.');

        if (!$this->pacientes->existeActivo($u->tenantId, $paciente)) {         // A-04, A-05 / E-03
            throw new PacienteNoEncontrado();
        }
        if ($this->admisiones->tieneAdmisionActiva($u->tenantId, $paciente)) { // A-06 / E-04, RN-02
            throw new PacienteConAdmisionActiva();
        }
        $cama = $this->catalogo->buscar($u->tenantId, $s->camaId)               // A-07, RN-04
            ?? throw new CamaNoEncontrada();
        if (in_array($cama->id, $this->admisiones->camasOcupadas($u->tenantId), true)) {
            throw new CamaYaAsignada();                                          // aviso temprano; la garantía es A-12
        }

        $admision = Admision::abrir(                                             // A-08 / E-05, A-14
            $this->ids->nuevo(), $u->tenantId, $paciente, $requerimiento, $origen,
            $cama, $this->evaluador, $u->responsable(), $this->reloj->ahora(),
        );
        $this->admisiones->registrar($admision);                                 // A-11, A-12 / E-06, A-15, RN-01

        return ResultadoAdmision::desde($admision);                              // A-16
    }
}
