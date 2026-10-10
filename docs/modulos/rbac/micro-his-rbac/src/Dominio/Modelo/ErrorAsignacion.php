<?php

declare(strict_types=1);

namespace Rbac\Dominio\Modelo;

/** Rechazo de una regla de asignación, con su código HTTP sugerido. */
final class ErrorAsignacion
{
    public function __construct(
        public readonly string $codigo,
        public readonly int $codigoHttp,
    ) {
    }
}
