<?php

declare(strict_types=1);

namespace NotasSoap\EnMemoria;

use NotasSoap\Contratos\AutenticadorToken;
use NotasSoap\Dominio\UsuarioAutenticado;

final class AutenticadorTokenEnMemoria implements AutenticadorToken
{
    /** @param array<string, UsuarioAutenticado> $tokens */
    public function __construct(private readonly array $tokens)
    {
    }

    public function usuarioDesdeToken(string $token): ?UsuarioAutenticado
    {
        return $this->tokens[$token] ?? null;
    }
}
