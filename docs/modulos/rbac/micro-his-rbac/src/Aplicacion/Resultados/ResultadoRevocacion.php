<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\Resultados;

/** Salida de RevocarRol: 200, 403, 404, 422 o 500. */
final class ResultadoRevocacion
{
    private function __construct(
        public readonly bool $exito,
        public readonly int $codigoHttp,
        public readonly string $codigo,
        public readonly ?string $correlationId = null,
    ) {
    }

    public static function revocado(string $correlationId): self
    {
        return new self(true, 200, 'ROL_REVOCADO', $correlationId);
    }

    public static function denegado(?string $correlationId): self
    {
        return new self(false, 403, 'ACCESO_DENEGADO', $correlationId);
    }

    public static function rechazado(string $codigo, int $codigoHttp): self
    {
        return new self(false, $codigoHttp, $codigo);
    }

    public static function errorPersistencia(): self
    {
        return new self(false, 500, 'ERROR_PERSISTENCIA');
    }
}
