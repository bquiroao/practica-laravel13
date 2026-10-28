<?php

declare(strict_types=1);

namespace Pacientes\Servicios;

use Pacientes\Contratos\IndiceMaestro;
use Pacientes\Contratos\RegistroAuditoria;
use Pacientes\Contratos\RepositorioPacientes;
use Pacientes\Contratos\Transaccion;
use Pacientes\Dominio\ErrorPaciente;
use Pacientes\Dominio\EventoAuditoria;
use Pacientes\Dominio\Paciente;
use Pacientes\Dominio\Respuesta;
use Pacientes\Dominio\SolicitudActualizacion;

/** CU-04: actualización segura (estado, versión, motivo, unicidad y auditoría antes/después). */
final class ServicioActualizacionPacientes
{
    public function __construct(
        private readonly RepositorioPacientes $repositorio,
        private readonly IndiceMaestro $indice,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly ControlAcceso $acceso,
        private readonly \DateTimeImmutable $hoy,
    ) {
    }

    public function actualizar(SolicitudActualizacion $solicitud): Respuesta
    {
        try {
            $this->acceso->exigir($solicitud->actor, $solicitud->hospital, 'pacientes.editar');
            $actual = $this->repositorio->porCodigo($solicitud->hospital, $solicitud->codigo)
                ?? throw new ErrorPaciente(404, 'PACIENTE_NO_ENCONTRADO');

            // R-08: el estado es un dato y la regla está aquí, no en una subclase PacienteFallecido.
            if ($actual->estado !== Paciente::ACTIVO
                && !$this->acceso->tiene($solicitud->actor, $solicitud->hospital, 'pacientes.editar_restringido')) {
                throw new ErrorPaciente(403, 'REQUIERE_SUPERVISOR');
            }

            $nuevo = $actual->conDatosDe(Paciente::desdeDatos($solicitud->hospital, $solicitud->datos, $this->hoy));
            $motivo = trim((string) $solicitud->motivo);
            if ($nuevo->cambiaIdentidadRespectoDe($actual)) {
                $this->verificarCambioDeIdentidad($actual, $nuevo, $motivo);
            }

            // R-06: la versión la compara el repositorio, de forma atómica, como exige su contrato.
            $guardado = $this->transaccion->ejecutar(function () use ($solicitud, $actual, $nuevo, $motivo): Paciente {
                $guardado = $this->repositorio->actualizar($nuevo, $solicitud->versionLeida);
                $this->indice->indexar($guardado);
                $this->auditoria->registrar(new EventoAuditoria('PACIENTE_ACTUALIZADO', $solicitud->actor, $solicitud->hospital,
                    $guardado->codigo, [
                        'antes' => $actual->instantanea(),
                        'despues' => $guardado->instantanea(),
                        'motivo' => $motivo === '' ? null : $motivo,
                    ]));

                return $guardado;
            });

            return new Respuesta(200, ['codigo' => $guardado->codigo, 'version' => $guardado->version]);
        } catch (ErrorPaciente $e) {
            return Respuesta::desdeError($e);
        } catch (\Throwable) {
            return Respuesta::errorInterno();
        }
    }

    /** R-07: motivo obligatorio y, si hay documento, que no pertenezca a otro paciente (R-02). */
    private function verificarCambioDeIdentidad(Paciente $actual, Paciente $nuevo, string $motivo): void
    {
        if ($motivo === '') {
            throw new ErrorPaciente(422, 'MOTIVO_REQUERIDO');
        }
        if ($nuevo->identificador === null) {
            return;
        }
        $titular = $this->indice->titularDe($nuevo->identificador->claveBusqueda());
        if ($titular !== null && $titular !== $actual->codigo) {
            throw new ErrorPaciente(409, 'IDENTIFICADOR_EN_USO', ['identificador' => $nuevo->identificador->enmascarado()]);
        }
    }
}
