<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http\Formularios;

use Auditoria\Aplicacion\Comandos\ComandoConsultar;
use Auditoria\Aplicacion\Comandos\ComandoDetalle;
use Auditoria\Aplicacion\Comandos\ComandoRegistrar;
use Auditoria\Aplicacion\Comandos\ComandoVerificar;
use Auditoria\Presentacion\Http\Identidad;
use Auditoria\Presentacion\Http\Solicitud;

/**
 * MVC — la parte de la ENTRADA del controlador: de la petición a un comando. Solo valida
 * FORMATO (cuerpo JSON, tipos). Actor y hospital salen del token y de la cabecera,
 * nunca del cuerpo: un emisor no puede declararse otro emisor.
 */
final class FormularioMovimientos
{
    /** @throws EntradaInvalida */
    public static function registro(Solicitud $solicitud, Identidad $identidad): ComandoRegistrar
    {
        return new ComandoRegistrar($identidad->actor, $solicitud->cabecera('X-Tenant-ID'), self::cuerpo($solicitud));
    }

    /** @throws EntradaInvalida */
    public static function consulta(Solicitud $solicitud, Identidad $identidad): ComandoConsultar
    {
        foreach ($solicitud->consulta as $nombre => $valor) {
            if (!is_string($valor)) {
                throw new EntradaInvalida(EntradaInvalida::FORMATO, [(string) $nombre => 'Un solo valor de texto.']);
            }
        }

        return new ComandoConsultar($identidad->actor, $solicitud->cabecera('X-Tenant-ID'), $solicitud->consulta);
    }

    /** @param array<string, string> $parametros parámetros de la ruta (el id ya viene validado por su patrón) */
    public static function detalle(Solicitud $solicitud, Identidad $identidad, array $parametros): ComandoDetalle
    {
        return new ComandoDetalle($identidad->actor, $solicitud->cabecera('X-Tenant-ID'), (int) $parametros['id']);
    }

    /** @throws EntradaInvalida */
    public static function verificacion(Solicitud $solicitud, Identidad $identidad): ComandoVerificar
    {
        $cuerpo = self::cuerpo($solicitud);
        $hospital = $cuerpo['hospital'] ?? null;
        if ($hospital !== null && !is_string($hospital)) {
            throw new EntradaInvalida(EntradaInvalida::FORMATO, ['hospital' => 'Texto, o null para la cadena de red.']);
        }

        return new ComandoVerificar($identidad->actor, $hospital);
    }

    /**
     * @return array<string, mixed>
     * @throws EntradaInvalida
     */
    private static function cuerpo(Solicitud $solicitud): array
    {
        if ($solicitud->cuerpo === null || array_is_list($solicitud->cuerpo) && $solicitud->cuerpo !== []) {
            throw new EntradaInvalida(EntradaInvalida::CUERPO);
        }

        return $solicitud->cuerpo;
    }
}
