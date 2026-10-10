<?php

declare(strict_types=1);

namespace Rbac\Dominio;

/** Resultado de asignar un rol: 201, 403, 404, 409, 422 o 500. */
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

    public static function denegado(Decision $decision): self
    {
        return new self(false, 403, (string) $decision->motivo, $decision->correlationId);
    }

    public static function rechazado(ErrorAsignacion $error): self
    {
        return new self(false, $error->codigoHttp, $error->codigo);
    }

    public static function fallaAuditoria(): self
    {
        return new self(false, 500, 'FALLA_AUDITORIA');
    }
}
