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
use Pacientes\Dominio\SolicitudRegistro;

/** CU-01, CU-05, CU-06: registrar un paciente sin crear duplicados. */
final class ServicioRegistroPacientes
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

    public function registrar(SolicitudRegistro $solicitud): Respuesta
    {
        try {
            $this->acceso->exigir($solicitud->actor, $solicitud->hospital, 'pacientes.registrar');
            $paciente = Paciente::desdeDatos($solicitud->hospital, $solicitud->datos, $this->hoy);
            $confirmadas = $this->verificarDuplicados($solicitud, $paciente);

            $registrado = $this->transaccion->ejecutar(function () use ($solicitud, $paciente, $confirmadas): Paciente {
                $registrado = $this->repositorio->registrar($paciente);
                $this->indice->indexar($registrado);
                $this->auditoria->registrar(new EventoAuditoria('PACIENTE_REGISTRADO', $solicitud->actor, $solicitud->hospital,
                    $registrado->codigo, [
                        'datos' => $registrado->instantanea(),
                        'coincidencias_confirmadas' => $confirmadas,
                        'motivo' => $confirmadas === [] ? null : trim((string) $solicitud->motivo),
                    ]));

                return $registrado;
            });

            return new Respuesta(201, [
                'codigo' => $registrado->codigo,
                'version' => $registrado->version,
                'identidad_provisional' => $registrado->identidadProvisional(),
            ]);
        } catch (ErrorPaciente $e) {
            return Respuesta::desdeError($e);
        } catch (\Throwable) {
            return Respuesta::errorInterno();
        }
    }

    /**
     * R-02: el mismo documento bloquea. R-03: nombre y fecha iguales advierten, salvo que
     * un supervisor confirme con motivo. Solo depende del contrato de Identificador:
     * si el paciente no tiene documento, no hay clave que comparar.
     *
     * @return list<string> coincidencias confirmadas por el supervisor
     */
    private function verificarDuplicados(SolicitudRegistro $solicitud, Paciente $paciente): array
    {
        if ($paciente->identificador !== null) {
            $titular = $this->indice->titularDe($paciente->identificador->claveBusqueda());
            if ($titular !== null) {
                throw new ErrorPaciente(409, 'PACIENTE_EXISTENTE', [
                    'codigo' => $titular,
                    'identificador' => $paciente->identificador->enmascarado(),
                ]);
            }
        }

        $coincidencias = $this->indice->coincidenciasProbables($paciente->nombreNormalizado(), $paciente->nacimiento->format('Y-m-d'));
        if ($coincidencias === []) {
            return [];
        }
        if (!$solicitud->confirmaPeseACoincidencia) {
            throw new ErrorPaciente(409, 'POSIBLE_DUPLICADO', ['candidatos' => $coincidencias]);
        }
        $this->acceso->exigir($solicitud->actor, $solicitud->hospital, 'pacientes.confirmar_duplicado');
        if (trim((string) $solicitud->motivo) === '') {
            throw new ErrorPaciente(422, 'MOTIVO_REQUERIDO');
        }

        return $coincidencias;
    }
}
