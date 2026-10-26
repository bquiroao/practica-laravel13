<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http;

final class AutenticadorTokenDemo implements Autenticador
{
    /** @param array<string, array{0: string, 1: string, 2: list<string>}> $tokens token => [usuario, hospital, roles] */
    public function __construct(private readonly array $tokens)
    {
    }

    public function identidad(string $token): ?Identidad
    {
        $t = $this->tokens[$token] ?? null;

        return $t === null ? null : new Identidad($t[0], $t[1], $t[2]);
    }
}
