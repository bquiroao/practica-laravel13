<?php

declare(strict_types=1);

namespace Rbac\Dominio;

/** Rechazo de una regla de asignación, con su código HTTP. */
final class ErrorAsignacion
{
    public function __construct(
        public readonly string $codigo,
        public readonly int $codigoHttp,
    ) {
    }
}
