<?php

declare(strict_types=1);

namespace Rbac\Dominio\Modelo;

/** Rechazo de una regla de revocación, con su código HTTP sugerido. */
final class ErrorRevocacion
{
    public function __construct(
        public readonly string $codigo,
        public readonly int $codigoHttp,
    ) {
    }
}
