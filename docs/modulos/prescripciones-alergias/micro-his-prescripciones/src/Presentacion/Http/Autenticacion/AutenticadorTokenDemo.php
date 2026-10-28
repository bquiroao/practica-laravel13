<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http\Autenticacion;

use Prescripciones\Presentacion\Http\Identidad;

/** Tokens de demostración (datos ficticios). En el HIS la identidad la entrega JwtAuth detrás del mismo contrato. */
final class AutenticadorTokenDemo implements Autenticador
{
    /** @param array<string, array{0: string, 1: string}> $tokens token => [usuario, hospital] */
    public function __construct(private readonly array $tokens)
    {
    }

    public function autenticar(?string $cabeceraAuthorization): ?Identidad
    {
        if ($cabeceraAuthorization === null || !preg_match('/^Bearer\s+(\S+)$/', $cabeceraAuthorization, $m)) {
            return null;
        }
        $datos = $this->tokens[$m[1]] ?? null;

        return $datos === null ? null : new Identidad($datos[0], $datos[1]);
    }
}
