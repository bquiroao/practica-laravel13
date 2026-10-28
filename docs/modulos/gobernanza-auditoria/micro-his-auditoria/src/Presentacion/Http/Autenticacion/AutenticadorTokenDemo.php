<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http\Autenticacion;

use Auditoria\Presentacion\Http\Identidad;

/** Tokens fijos de demostración (datos ficticios). Solo para desarrollo y pruebas. */
final class AutenticadorTokenDemo implements Autenticador
{
    /** @param array<string, array{0: string, 1: string}> $tokens token => [actor, hospital] */
    public function __construct(private readonly array $tokens)
    {
    }

    public function identificar(string $token): ?Identidad
    {
        foreach ($this->tokens as $conocido => [$actor, $hospital]) {
            if (hash_equals($conocido, $token)) {
                return new Identidad($actor, $hospital);
            }
        }

        return null;
    }
}
