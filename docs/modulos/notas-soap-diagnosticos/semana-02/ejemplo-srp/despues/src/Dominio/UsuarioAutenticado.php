<?php

declare(strict_types=1);

namespace NotasSoap\Dominio;

/** Lo que se sabe del usuario a partir de su token. El autor de la nota sale de aquí, nunca del cuerpo de la petición. */
final class UsuarioAutenticado
{
    /** @param list<string> $roles */
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly array $roles,
    ) {
    }

    public function tieneRol(string $rol): bool
    {
        return in_array($rol, $this->roles, true);
    }
}
