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
use Pacientes\Dominio\Servicios\PoliticaConfidencialidad;
use Pacientes\Dominio\Servicios\PoliticaDuplicados;
use Pacientes\Dominio\Servicios\PoliticaEdicion;

/** CU-04: actualización segura (estado, motivo, unicidad, versión y auditoría antes/después). */
final class ActualizarPaciente
{
    public function __construct(
        private readonly RepositorioPacientes $repositorio,
        private readonly PoliticaEdicion $edicion,
        private readonly PoliticaDuplicados $duplicados,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly ControlAcceso $acceso,
        private readonly Reloj $reloj,
        private readonly PoliticaConfidencialidad $confidencialidad,
    ) {
    }

    public function ejecutar(ComandoActualizar $comando): Resultado
    {
        try {
            $this->acceso->exigir($comando->actor, $comando->hospital, ControlAcceso::EDITAR);
            $actual = $this->repositorio->porCodigo($comando->hospital, strtoupper($comando->codigo))
                ?? throw new PacienteNoEncontrado();

            // R-11: no se edita lo que no se ve (antes de validar, para no revelar nada).
            $this->confidencialidad->exigirVisibilidad($actual,
                $this->acceso->tiene($comando->actor, $comando->hospital, ControlAcceso::LEER_PROTEGIDO));

            $datos = FormularioPaciente::aDatos($comando->datos);
            $nuevo = $actual->conDatosDe(Paciente::desdeDatos($comando->hospital, $datos, $this->reloj->hoy()));
            if ($datos->identidadProtegida === null) {
                $nuevo = $nuevo->conProteccion($actual->identidadProtegida); // campo ausente = sin cambio
            }
            $motivo = trim((string) $comando->motivo);
            $this->edicion->verificar($actual, $nuevo,
                $this->acceso->tiene($comando->actor, $comando->hospital, ControlAcceso::EDITAR_RESTRINGIDO), $motivo);
            $this->confidencialidad->verificarCambio($actual, $nuevo,
                $this->acceso->tiene($comando->actor, $comando->hospital, ControlAcceso::PROTEGER), $motivo);
            if ($nuevo->cambiaIdentidadRespectoDe($actual)) {
                $this->duplicados->verificarDocumentoNuevo($actual, $nuevo);
            }

            // R-06: la versión la compara el repositorio al escribir (atómico); R-09: misma transacción.
            $guardado = $this->transaccion->ejecutar(function () use ($comando, $actual, $nuevo, $motivo): Paciente {
                $guardado = $this->repositorio->actualizar($nuevo, $comando->versionLeida);
                $this->auditoria->registrar(new EventoAuditoria(EventoAuditoria::ACTUALIZADO, $comando->actor, $comando->hospital,
                    $guardado->codigo, [
                        'antes' => $actual->instantanea(),
                        'despues' => $guardado->instantanea(),
                        'motivo' => $motivo === '' ? null : $motivo,
                    ]));

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
