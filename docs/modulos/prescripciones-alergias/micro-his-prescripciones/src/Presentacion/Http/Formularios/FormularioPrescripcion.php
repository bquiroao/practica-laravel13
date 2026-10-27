<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http\Formularios;

use Prescripciones\Dominio\Modelo\SolicitudEmision;
use Prescripciones\Presentacion\Http\Identidad;
use Prescripciones\Presentacion\Http\Solicitud;

/**
 * Entrada del MVC: convierte la petición HTTP en la solicitud del caso de uso. Solo FORMATO
 * (JSON, tipos, cabeceras). El usuario y el hospital salen de la Identidad, nunca del cuerpo.
 */
final class FormularioPrescripcion
{
    /** @throws EntradaInvalida */
    public static function emision(Solicitud $solicitud, Identidad $identidad): SolicitudEmision
    {
        $clave = $solicitud->cabecera('idempotency-key');
        if ($clave === null || !preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $clave)) {
            throw new EntradaInvalida('IDEMPOTENCY_KEY_REQUERIDA');
        }
        $datos = self::cuerpo($solicitud);
        $justificacion = $datos['justificacion'] ?? null;
        $confirma = $datos['confirma_sin_alergias'] ?? false;
        if (($justificacion !== null && !is_string($justificacion)) || !is_bool($confirma)) {
            throw new EntradaInvalida('CUERPO_INVALIDO');
        }

        return new SolicitudEmision($identidad->usuario, $identidad->hospital, $datos['paciente'], $clave, $datos['items'], $justificacion, $confirma);
    }

    /** @throws EntradaInvalida */
    public static function verificacion(Solicitud $solicitud): EntradaVerificacion
    {
        $datos = self::cuerpo($solicitud);

        return new EntradaVerificacion($datos['paciente'], $datos['items']);
    }

    /** @throws EntradaInvalida */
    public static function id(array $parametros): int
    {
        return (int) $parametros['id'];
    }

    /** @return array{paciente: string, items: list<mixed>, justificacion?: mixed, confirma_sin_alergias?: mixed} */
    private static function cuerpo(Solicitud $solicitud): array
    {
        try {
            $datos = json_decode($solicitud->cuerpo, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new EntradaInvalida('JSON_INVALIDO');
        }
        if (!is_array($datos) || !is_string($datos['paciente'] ?? null) || !is_array($datos['items'] ?? null) || !array_is_list($datos['items'])) {
            throw new EntradaInvalida('CUERPO_INVALIDO');
        }

        return $datos;
    }
}
