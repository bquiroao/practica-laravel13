<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\CasosDeUso;

use Pacientes\Aplicacion\Comandos\ComandoRegistrar;
use Pacientes\Aplicacion\Comandos\FormularioPaciente;
use Pacientes\Aplicacion\Puertos\AlmacenIdempotencia;
use Pacientes\Aplicacion\Puertos\Reloj;
use Pacientes\Aplicacion\Puertos\Transaccion;
use Pacientes\Aplicacion\Resultados\Resultado;
use Pacientes\Aplicacion\Servicios\ControlAcceso;
use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Dominio\Puertos\RegistroAuditoria;
use Pacientes\Dominio\Puertos\RepositorioPacientes;
use Pacientes\Dominio\Servicios\PoliticaConfidencialidad;
use Pacientes\Dominio\Servicios\PoliticaDuplicados;

/** CU-01, CU-05 y CU-06: registrar un paciente sin crear duplicados, ni siquiera al reintentar. */
final class RegistrarPaciente
{
    public function __construct(
        private readonly RepositorioPacientes $repositorio,
        private readonly PoliticaDuplicados $duplicados,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly ControlAcceso $acceso,
        private readonly Reloj $reloj,
        private readonly AlmacenIdempotencia $idempotencia,
        private readonly PoliticaConfidencialidad $confidencialidad,
    ) {
    }

    public function ejecutar(ComandoRegistrar $comando): Resultado
    {
        try {
            $this->acceso->exigir($comando->actor, $comando->hospital, ControlAcceso::REGISTRAR);

            // Semana 5: un reintento con la misma Idempotency-Key devuelve la respuesta original.
            $huella = null;
            if ($comando->claveIdempotencia !== null) {
                $huella = hash('sha256', (string) json_encode([$comando->datos, $comando->confirmaPeseACoincidencia, $comando->motivo]));
                $previa = $this->idempotencia->buscar($comando->hospital, $comando->actor, $comando->claveIdempotencia);
                if ($previa !== null) {
                    if (!hash_equals($previa['huella'], $huella)) {
                        throw new ErrorDominio(ErrorDominio::INVALIDO, 'IDEMPOTENCY_KEY_REUTILIZADA');
                    }

                    return Resultado::creado($previa['datos'] + ['repetida' => true]);
                }
            }

            $paciente = Paciente::desdeDatos($comando->hospital, FormularioPaciente::aDatos($comando->datos), $this->reloj->hoy());
            // Semana 6 (R-11): proteger la identidad exige pacientes.proteger y motivo.
            $this->confidencialidad->verificarRegistro($paciente,
                $this->acceso->tiene($comando->actor, $comando->hospital, ControlAcceso::PROTEGER), (string) $comando->motivo);
            $confirmadas = $this->duplicados->verificarRegistro(
                $paciente,
                $comando->confirmaPeseACoincidencia,
                $this->acceso->tiene($comando->actor, $comando->hospital, ControlAcceso::CONFIRMAR_DUPLICADO),
                (string) $comando->motivo,
                $this->acceso->tiene($comando->actor, $comando->hospital, ControlAcceso::LEER_PROTEGIDO),
            );

            // A-07: paciente, evento y clave de idempotencia se guardan juntos o no se guarda nada (R-09).
            $datos = $this->transaccion->ejecutar(function () use ($comando, $paciente, $confirmadas, $huella): array {
                $registrado = $this->repositorio->registrar($paciente);
                $this->auditoria->registrar(new EventoAuditoria(EventoAuditoria::REGISTRADO, $comando->actor, $comando->hospital,
                    $registrado->codigo, [
                        'datos' => $registrado->instantanea(),
                        'coincidencias_confirmadas' => $confirmadas,
                        'motivo' => $confirmadas === [] ? null : trim((string) $comando->motivo),
                        'motivo_proteccion' => $registrado->identidadProtegida ? trim((string) $comando->motivo) : null,
                    ]));
                $datos = [
                    'codigo' => $registrado->codigo,
                    'version' => $registrado->version,
                    'identidad_provisional' => $registrado->identidadProvisional(),
                ];
                if ($comando->claveIdempotencia !== null) {
                    $this->idempotencia->guardar($comando->hospital, $comando->actor, $comando->claveIdempotencia, (string) $huella, $datos);
                }

                return $datos;
            });

            return Resultado::creado($datos);
        } catch (ErrorDominio $e) {
            return Resultado::desdeError($e);
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }
}
