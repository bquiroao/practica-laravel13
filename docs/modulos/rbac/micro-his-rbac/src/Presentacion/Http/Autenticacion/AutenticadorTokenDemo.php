<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Autenticacion;

use Rbac\Presentacion\Http\Identidad;
use Rbac\Presentacion\Http\Solicitud;

/**
 * Autenticación de demostración: tokens fijos «Bearer <token>» → (usuario, hospital).
 * La autenticación real (JWT) es del módulo de usuarios; está fuera del alcance de RBAC.
 */
final class AutenticadorTokenDemo implements Autenticador
{
    /** @param array<string, array{0: string, 1: string}> $tokens token => [usuarioId, hospitalId] */
    public function __construct(private readonly array $tokens)
    {
    }

    public function autenticar(Solicitud $solicitud): ?Identidad
    {
        if (preg_match('/^Bearer\s+(\S+)$/i', $solicitud->cabecera('Authorization'), $m) !== 1) {
            return null;
        }

        $datos = $this->tokens[$m[1]] ?? null;

        return $datos === null ? null : new Identidad($datos[0], $datos[1]);
    }
}
