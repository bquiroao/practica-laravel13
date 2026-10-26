<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\CasosDeUso;

use Pacientes\Aplicacion\Comandos\ComandoActualizar;
use Pacientes\Aplicacion\Comandos\FormularioPaciente;
use Pacientes\Aplicacion\Puertos\Reloj;
use Pacientes\Aplicacion\Puertos\Transaccion;
use Pacientes\Aplicacion\Resultados\Resultado;
use Pacientes\Aplicacion\Servicios\ControlAcceso;
use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Excepciones\PacienteNoEncontrado;
use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Dominio\Puertos\RegistroAuditoria;
use Pacientes\Dominio\Puertos\RepositorioPacientes;
use Pacientes\Dominio\Servicios\RevisionDeFicha;

/**
 * CU-04: actualización segura (estado, motivo, unicidad, versión y auditoría antes/después).
 * Semana 7: solo orquesta (permiso → cargar → revisión de la ficha → guardar con evidencia);
 * las reglas de la ficha y su orden viven en RevisionDeFicha.
 */
final class ActualizarPaciente
{
    public function __construct(
        private readonly RepositorioPacientes $repositorio,
        private readonly RevisionDeFicha $revision,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly ControlAcceso $acceso,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(ComandoActualizar $comando): Resultado
    {
        try {
            $this->acceso->exigir($comando->actor, $comando->hospital, ControlAcceso::EDITAR);
            $actual = $this->repositorio->porCodigo($comando->hospital, strtoupper($comando->codigo))
                ?? throw new PacienteNoEncontrado();

            $motivo = (string) $comando->motivo;
            $nuevo = $this->revision->paraEdicion($actual, FormularioPaciente::aDatos($comando->datos), $this->reloj->hoy(),
                $this->acceso->capacidades($comando->actor, $comando->hospital), $motivo);

            // R-06: la versión la compara el repositorio al escribir (atómico); R-09: misma transacción.
            $guardado = $this->transaccion->ejecutar(function () use ($comando, $actual, $nuevo, $motivo): Paciente {
                $guardado = $this->repositorio->actualizar($nuevo, $comando->versionLeida);
                $this->auditoria->registrar(EventoAuditoria::actualizado($comando->actor, $actual, $guardado, $motivo));

                return $guardado;
            });

            return Resultado::ok(['codigo' => $guardado->codigo, 'version' => $guardado->version]);
        } catch (ErrorDominio $e) {
            return Resultado::desdeError($e);
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }
}
