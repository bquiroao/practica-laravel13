<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\CasosDeUso;

use Pacientes\Aplicacion\Comandos\ComandoConsultar;
use Pacientes\Aplicacion\Resultados\Resultado;
use Pacientes\Aplicacion\Servicios\ControlAcceso;
use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Excepciones\PacienteNoEncontrado;
use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Dominio\Puertos\ConsultaPacientes;
use Pacientes\Dominio\Puertos\RegistroAuditoria;

/** CU-03: detalle con su versión. El mismo 404 si el paciente es de otro hospital. */
final class ConsultarPaciente
{
    public function __construct(
        private readonly ConsultaPacientes $consulta,
        private readonly RegistroAuditoria $auditoria,
        private readonly ControlAcceso $acceso,
    ) {
    }

    public function ejecutar(ComandoConsultar $comando): Resultado
    {
        try {
            $this->acceso->exigir($comando->actor, $comando->hospital, ControlAcceso::LEER);
            $paciente = $this->consulta->porCodigo($comando->hospital, strtoupper($comando->codigo))
                ?? throw new PacienteNoEncontrado();
            // R-11: un protegido se muestra sin demografía a quien no tiene pacientes.leer_protegido,
            // y la auditoría distingue ese acceso limitado.
            $verProtegidos = $this->acceso->capacidades($comando->actor, $comando->hospital)->verProtegidos;
            $this->auditoria->registrar(new EventoAuditoria(EventoAuditoria::CONSULTADO, $comando->actor, $comando->hospital, $paciente->codigo,
                ['acceso_limitado' => $paciente->seMuestraReducidoPara($verProtegidos)]));

            return Resultado::ok(['paciente' => $paciente->detalle($verProtegidos)]);
        } catch (ErrorDominio $e) {
            return Resultado::desdeError($e);
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }
}
