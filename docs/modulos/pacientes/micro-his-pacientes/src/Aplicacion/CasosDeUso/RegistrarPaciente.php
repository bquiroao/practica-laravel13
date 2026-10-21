<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\CasosDeUso;

use Pacientes\Aplicacion\Comandos\ComandoRegistrar;
use Pacientes\Aplicacion\Comandos\FormularioPaciente;
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
use Pacientes\Dominio\Servicios\PoliticaDuplicados;

/** CU-01, CU-05 y CU-06: registrar un paciente sin crear duplicados. */
final class RegistrarPaciente
{
    public function __construct(
        private readonly RepositorioPacientes $repositorio,
        private readonly PoliticaDuplicados $duplicados,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly ControlAcceso $acceso,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(ComandoRegistrar $comando): Resultado
    {
        try {
            $this->acceso->exigir($comando->actor, $comando->hospital, ControlAcceso::REGISTRAR);
            $paciente = Paciente::desdeDatos($comando->hospital, FormularioPaciente::aDatos($comando->datos), $this->reloj->hoy());
            $confirmadas = $this->duplicados->verificarRegistro(
                $paciente,
                $comando->confirmaPeseACoincidencia,
                $this->acceso->tiene($comando->actor, $comando->hospital, ControlAcceso::CONFIRMAR_DUPLICADO),
                (string) $comando->motivo,
            );

            // A-07: el paciente y su evento se guardan juntos o no se guarda nada (R-09).
            $registrado = $this->transaccion->ejecutar(function () use ($comando, $paciente, $confirmadas): Paciente {
                $registrado = $this->repositorio->registrar($paciente);
                $this->auditoria->registrar(new EventoAuditoria(EventoAuditoria::REGISTRADO, $comando->actor, $comando->hospital,
                    $registrado->codigo, [
                        'datos' => $registrado->instantanea(),
                        'coincidencias_confirmadas' => $confirmadas,
                        'motivo' => $confirmadas === [] ? null : trim((string) $comando->motivo),
                    ]));

                return $registrado;
            });

            return Resultado::creado([
                'codigo' => $registrado->codigo,
                'version' => $registrado->version,
                'identidad_provisional' => $registrado->identidadProvisional(),
            ]);
        } catch (ErrorDominio $e) {
            return Resultado::desdeError($e);
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }
}
