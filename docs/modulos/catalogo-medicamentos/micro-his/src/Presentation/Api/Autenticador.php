<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Api;

/**
 * Tokens FICTICIOS de desarrollo (config.php > api > tokens). En el HIS se reemplaza por el
 * mecanismo real (por ejemplo, Sanctum de Laravel); el resto de la API no cambia.
 */
final class Autenticador
{
    /** @param array<string, array{usuario: string, rol: string, hospital: string}> $tokens */
    public function __construct(private readonly array $tokens)
    {
    }

    /** null si falta el encabezado o el token no existe (401). */
    public function identificar(?string $autorizacion): ?Identidad
    {
        if ($autorizacion === null || preg_match('/^Bearer\s+([A-Za-z0-9._-]{8,200})$/', trim($autorizacion), $m) !== 1) {
            return null;
        }
        foreach ($this->tokens as $token => $datos) {
            if (hash_equals($token, $m[1])) {
                return new Identidad($datos['usuario'], $datos['rol'], $datos['hospital']);
            }
        }
        return null;
    }
}
