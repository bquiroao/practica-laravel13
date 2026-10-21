<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Acciones;

use Pacientes\Aplicacion\CasosDeUso\ActualizarPaciente;
use Pacientes\Aplicacion\Comandos\ComandoActualizar;
use Pacientes\Presentacion\Http\Accion;
use Pacientes\Presentacion\Http\Identidad;
use Pacientes\Presentacion\Http\Respuesta;
use Pacientes\Presentacion\Http\Solicitud;
use Pacientes\Presentacion\Http\TraductorResultado;

/**
 * PUT /api/v1/pacientes/{codigo} con If-Match: "<versión leída>" (RFC 9110 §13.1.1).
 * Sin If-Match responde 428 (RFC 6585): no se permite editar «a ciegas».
 */
final class ActualizarPacienteAccion implements Accion
{
    public function __construct(private readonly ActualizarPaciente $actualizar)
    {
    }

    public function ejecutar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        $ifMatch = $solicitud->cabecera('If-Match');
        if ($ifMatch === '') {
            return Respuesta::error(428, 'VERSION_REQUERIDA');
        }
        if (preg_match('/^(?:W\/)?"?(\d{1,9})"?$/', $ifMatch, $m) !== 1) {
            return Respuesta::error(400, 'IF_MATCH_INVALIDO');
        }
        if ($solicitud->cuerpo === null) {
            return Respuesta::error(400, 'CUERPO_INVALIDO');
        }

        $resultado = $this->actualizar->ejecutar(new ComandoActualizar(
            $identidad->actor,
            $identidad->hospital,
            $parametros['codigo'],
            (int) $m[1],
            EntradaPaciente::datos($solicitud->cuerpo),
            EntradaPaciente::motivo($solicitud->cuerpo),
        ));

        return TraductorResultado::aRespuesta($resultado, $resultado->exitoso()
            ? ['ETag' => '"' . $resultado->datos['version'] . '"']
            : []);
    }
}
