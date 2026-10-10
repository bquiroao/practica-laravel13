<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Modelo;

/** Quién hace la operación. El autor de la nota siempre sale de aquí. */
final class UsuarioClinico
{
    /** @param list<string> $roles */
    public function __construct(
        public readonly string $id,
        public readonly string $hospitalId,
        public readonly array $roles,
    ) {
    }

    public function tieneRol(string $rol): bool
    {
        return in_array($rol, $this->roles, true);
    }
}
