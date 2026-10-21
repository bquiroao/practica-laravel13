<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Acciones;

use Pacientes\Aplicacion\CasosDeUso\RegistrarPaciente;
use Pacientes\Aplicacion\Comandos\ComandoRegistrar;
use Pacientes\Presentacion\Http\Accion;
use Pacientes\Presentacion\Http\Identidad;
use Pacientes\Presentacion\Http\Respuesta;
use Pacientes\Presentacion\Http\Solicitud;
use Pacientes\Presentacion\Http\TraductorResultado;

/** POST /api/v1/pacientes */
final class RegistrarPacienteAccion implements Accion
{
    public function __construct(private readonly RegistrarPaciente $registrar)
    {
    }

    public function ejecutar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        if ($solicitud->cuerpo === null) {
            return Respuesta::error(400, 'CUERPO_INVALIDO');
        }

        $resultado = $this->registrar->ejecutar(new ComandoRegistrar(
            $identidad->actor,
            $identidad->hospital,
            EntradaPaciente::datos($solicitud->cuerpo),
            ($solicitud->cuerpo['confirmar_coincidencia'] ?? false) === true,
            EntradaPaciente::motivo($solicitud->cuerpo),
        ));

        return TraductorResultado::aRespuesta($resultado, $resultado->exitoso() ? [
            'Location' => '/api/v1/pacientes/' . $resultado->datos['codigo'],
            'ETag' => '"' . $resultado->datos['version'] . '"',
        ] : []);
    }
}
