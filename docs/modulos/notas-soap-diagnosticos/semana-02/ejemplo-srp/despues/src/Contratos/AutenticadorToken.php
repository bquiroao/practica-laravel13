<?php

declare(strict_types=1);

namespace NotasSoap\Contratos;

use NotasSoap\Dominio\UsuarioAutenticado;

/** Puerto hacia el módulo 1 (JWT). Devuelve null si el token no es válido. */
interface AutenticadorToken
{
    public function usuarioDesdeToken(string $token): ?UsuarioAutenticado;
}
