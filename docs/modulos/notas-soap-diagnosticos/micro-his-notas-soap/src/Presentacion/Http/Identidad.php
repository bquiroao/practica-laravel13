<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http;

/** Lo que la Presentación sabe del usuario después de autenticarlo. */
final class Identidad
{
    /** @param list<string> $roles */
    public function __construct(public readonly string $usuarioId, public readonly string $hospitalId, public readonly array $roles)
    {
    }
}
