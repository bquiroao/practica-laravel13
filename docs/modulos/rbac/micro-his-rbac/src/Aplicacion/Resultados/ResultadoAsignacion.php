<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\Resultados;

/** Salida de AsignarRol: 201, 403, 404, 409, 422 o 500. */
final class ResultadoAsignacion
{
    private function __construct(
        public readonly bool $exito,
        public readonly int $codigoHttp,
        public readonly string $codigo,
        public readonly ?string $correlationId = null,
    ) {
    }

    public static function creado(string $correlationId): self
    {
        return new self(true, 201, 'ROL_ASIGNADO', $correlationId);
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
