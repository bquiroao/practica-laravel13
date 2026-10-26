<?php

declare(strict_types=1);

namespace Pacientes\Dominio;

/** Cálculo común del hash de búsqueda y del enmascarado de un identificador. */
final class ClaveIdentificador
{
    // En el HIS la clave vendría de la configuración segura, nunca del código.
    private const CLAVE_HMAC = 'clave-de-demostracion-no-productiva';

    public static function calcular(string $emisor, string $tipo, string $valor): string
    {
        return hash_hmac('sha256', $emisor . '|' . $tipo . '|' . $valor, self::CLAVE_HMAC);
    }

    public static function enmascarar(string $valor): string
    {
        return str_repeat('*', max(0, strlen($valor) - 4)) . substr($valor, -4);
    }
}
