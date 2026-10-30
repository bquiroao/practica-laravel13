<?php
declare(strict_types=1);

namespace Alergias\Presentacion\Api;

/**
 * Tokens FICTICIOS de demostración. En el HIS el token lo emite el módulo de autenticación
 * y el rol lo resuelve RBAC; aquí solo se simula ese contrato.
 */
final class AutenticadorTokens
{
    private const TOKENS = [
        'tok-med-h01' => ['7a1e0000-0000-4000-8000-0000000000a1', 'medico', 'H-01'],
        'tok-enf-h01' => ['7a1e0000-0000-4000-8000-0000000000a2', 'enfermeria', 'H-01'],
        'tok-adm-h01' => ['7a1e0000-0000-4000-8000-0000000000a9', 'admision', 'H-01'],
        'tok-med-h02' => ['7a1e0000-0000-4000-8000-0000000000b1', 'medico', 'H-02'],
    ];

    public function identificar(?string $autorizacion): ?Identidad
    {
        if ($autorizacion === null || !preg_match('/^Bearer\s+(\S+)$/', $autorizacion, $m)) {
            return null;
        }
        $datos = self::TOKENS[$m[1]] ?? null;
        return $datos ? new Identidad(...$datos) : null;
    }
}
