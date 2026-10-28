<?php

declare(strict_types=1);

namespace Pacientes\Antes;

use Pacientes\Contratos\IndiceMaestro;
use Pacientes\Contratos\PoliticaAcceso;
use Pacientes\Contratos\RegistroAuditoria;
use Pacientes\Contratos\Transaccion;
use Pacientes\Dominio\CriterioBusqueda;
use Pacientes\Dominio\ErrorPaciente;
use Pacientes\Dominio\EventoAuditoria;
use Pacientes\Dominio\Paciente;
use Pacientes\Dominio\Respuesta;
use Pacientes\Dominio\SolicitudActualizacion;
use Pacientes\Dominio\SolicitudRegistro;

/**
 * DISEÑO ANTES — un solo servicio para registrar, buscar y actualizar.
 *
 * Síntoma típico de las violaciones de LSP: como los subtipos no se comportan como el
 * tipo base, el cliente pregunta con instanceof qué objeto recibió y aplica parches.
 * Cada parche hay que repetirlo en cada método; basta olvidar uno (ver actualizar()).
 */
final class ServicioPacientes
{
    public function __construct(
        private readonly RepositorioPacientes $repositorio,
        private readonly IndiceMaestro $indice,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly PoliticaAcceso $politica,
        private readonly \DateTimeImmutable $hoy,
    ) {
    }

    public function registrar(SolicitudRegistro $solicitud): Respuesta
    {
        // Parche por V2: la réplica «es» un repositorio, pero no se le puede pedir que registre.
        if ($this->repositorio instanceof ReplicaPacientes) {
            return new Respuesta(503, ['error' => 'MODO_SOLO_LECTURA']);
        }

        try {
            $this->exigir($solicitud->actor, $solicitud->hospital, 'pacientes.registrar');
            $paciente = Paciente::desdeDatos($solicitud->hospital, $solicitud->datos, $this->hoy);
            if ($paciente->identificador === null) {
                $paciente = $paciente->conIdentificador(new IdentificadorTemporal());
            }

            // Parche por V1: la clave del temporal es vacía y compartida, así que se salta.
            if (!($paciente->identificador instanceof IdentificadorTemporal)) {
                $titular = $this->indice->titularDe($paciente->identificador->claveBusqueda());
                if ($titular !== null) {
                    throw new ErrorPaciente(409, 'PACIENTE_EXISTENTE', ['codigo' => $titular, 'identificador' => $paciente->identificador->enmascarado()]);
                }
            }

            $coincidencias = $this->indice->coincidenciasProbables($paciente->nombreNormalizado(), $paciente->nacimiento->format('Y-m-d'));
            if ($coincidencias !== []) {
                if (!$solicitud->confirmaPeseACoincidencia) {
                    throw new ErrorPaciente(409, 'POSIBLE_DUPLICADO', ['candidatos' => $coincidencias]);
                }
                $this->exigir($solicitud->actor, $solicitud->hospital, 'pacientes.confirmar_duplicado');
                if (trim((string) $solicitud->motivo) === '') {
                    throw new ErrorPaciente(422, 'MOTIVO_REQUERIDO');
                }
            }

            $registrado = $this->transaccion->ejecutar(function () use ($solicitud, $paciente): Paciente {
                $registrado = $this->repositorio->registrar($paciente);
                $this->indice->indexar($registrado);
                $this->auditoria->registrar(new EventoAuditoria('PACIENTE_REGISTRADO', $solicitud->actor, $solicitud->hospital,
                    $registrado->codigo, ['datos' => $registrado->instantanea()]));

                return $registrado;
            });

            return new Respuesta(201, ['codigo' => $registrado->codigo, 'version' => $registrado->version]);
        } catch (ErrorPaciente $e) {
            return Respuesta::desdeError($e);
        } catch (\Throwable) {
            return Respuesta::errorInterno();
        }
    }

    /** @param array<string, ?string> $criterios */
    public function buscar(string $actor, string $hospital, array $criterios): Respuesta
    {
        try {
            $this->exigir($actor, $hospital, 'pacientes.buscar');
            $criterio = CriterioBusqueda::desde(...$criterios);
            $encontrados = $this->repositorio->buscar($hospital, $criterio);
            $this->auditoria->registrar(new EventoAuditoria('PACIENTE_BUSCADO', $actor, $hospital, null,
                ['criterio' => $criterio->resumenAuditable(), 'resultados' => count($encontrados)]));

            return new Respuesta(200, [
                'resultados' => array_map(static fn (Paciente $p): array => $p->resumen(), $encontrados),
                'total' => count($encontrados),
            ]);
        } catch (ErrorPaciente $e) {
            return Respuesta::desdeError($e);
        } catch (\Throwable) {
            return Respuesta::errorInterno();
        }
    }

    public function actualizar(SolicitudActualizacion $solicitud): Respuesta
    {
        if ($this->repositorio instanceof ReplicaPacientes) {
            return new Respuesta(503, ['error' => 'MODO_SOLO_LECTURA']);
        }

        try {
            $this->exigir($solicitud->actor, $solicitud->hospital, 'pacientes.editar');
            $actual = $this->repositorio->porCodigo($solicitud->hospital, $solicitud->codigo)
                ?? throw new ErrorPaciente(404, 'PACIENTE_NO_ENCONTRADO');
            if ($actual->estado !== Paciente::ACTIVO
                && !$this->politica->permite($solicitud->actor, $solicitud->hospital, 'pacientes.editar_restringido')) {
                throw new ErrorPaciente(403, 'REQUIERE_SUPERVISOR');
            }

            $nuevo = $actual->conDatosDe(Paciente::desdeDatos($solicitud->hospital, $solicitud->datos, $this->hoy));
            if ($nuevo->identificador === null) {
                $nuevo = $nuevo->conIdentificador(new IdentificadorTemporal());
            }
            $motivo = trim((string) $solicitud->motivo);
            if ($nuevo->cambiaIdentidadRespectoDe($actual)) {
                if ($motivo === '') {
                    throw new ErrorPaciente(422, 'MOTIVO_REQUERIDO');
                }
                // Aquí FALTA el parche de V1: con un temporal, titularDe('') devuelve a OTRO desconocido.
                $titular = $this->indice->titularDe($nuevo->identificador->claveBusqueda());
                if ($titular !== null && $titular !== $actual->codigo) {
                    throw new ErrorPaciente(409, 'IDENTIFICADOR_EN_USO', ['identificador' => $nuevo->identificador->enmascarado()]);
                }
            }

            $guardado = $this->transaccion->ejecutar(function () use ($solicitud, $actual, $nuevo, $motivo): Paciente {
                $guardado = $this->repositorio->actualizar($nuevo, $solicitud->versionLeida);
                $this->indice->indexar($guardado);
                $this->auditoria->registrar(new EventoAuditoria('PACIENTE_ACTUALIZADO', $solicitud->actor, $solicitud->hospital,
                    $guardado->codigo, ['antes' => $actual->instantanea(), 'despues' => $guardado->instantanea(), 'motivo' => $motivo]));

                return $guardado;
            });

            return new Respuesta(200, ['codigo' => $guardado->codigo, 'version' => $guardado->version]);
        } catch (ErrorPaciente $e) {
            return Respuesta::desdeError($e);
        } catch (\Throwable) {
            return Respuesta::errorInterno();
        }
    }

    private function exigir(string $actor, string $hospital, string $permiso): void
    {
        if (trim($hospital) === '') {
            throw new ErrorPaciente(400, 'HOSPITAL_REQUERIDO');
        }
        if (!$this->politica->permite($actor, $hospital, $permiso)) {
            throw new ErrorPaciente(403, 'ACCESO_DENEGADO');
        }
    }
}
