<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Autenticacion;

use Pacientes\Presentacion\Http\Identidad;
use Pacientes\Presentacion\Http\Solicitud;

/** Tokens fijos de demostración. En el HIS la identidad la entrega el middleware JwtAuth. */
final class AutenticadorTokenDemo implements Autenticador
{
    /** @param array<string, array{0: string, 1: string}> $tokens token => [actor, hospital] */
    public function __construct(private readonly array $tokens)
    {
    }

    public function autenticar(Solicitud $solicitud): ?Identidad
    {
        if (preg_match('/^Bearer\s+(\S+)$/', $solicitud->cabecera('Authorization'), $m) !== 1) {
            return null;
        }
        $datos = $this->tokens[$m[1]] ?? null;

        return $datos === null ? null : new Identidad($datos[0], $datos[1]);
    }
}
