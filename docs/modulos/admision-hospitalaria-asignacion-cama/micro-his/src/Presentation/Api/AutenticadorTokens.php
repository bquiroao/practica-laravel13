<?php
declare(strict_types=1);

namespace Admision\Presentation\Api;

use Admision\Application\ContextoUsuario;
use Admision\Presentation\UsuariosFicticios;

/**
 * Autenticación con tokens FICTICIOS (Bearer). El rol y el hospital (tenant) salen del token,
 * nunca del cuerpo de la petición (RNF-02). En producción se reemplaza por el proveedor de identidad del HIS.
 */
final class AutenticadorTokens
{
    /** @param array<string, string> $tokens token => usuario */
    public function __construct(private readonly array $tokens, private readonly UsuariosFicticios $usuarios)
    {
    }

    public function contexto(string $cabeceraAuthorization, string $solicitudId): ContextoUsuario
    {
        if (preg_match('/^Bearer ([A-Za-z0-9._-]{1,128})$/', $cabeceraAuthorization, $m) !== 1 || !isset($this->tokens[$m[1]])) {
            throw new ErrorApi('NO_AUTENTICADO', 'Falta un token válido en Authorization: Bearer <token>.');
        }
        return $this->usuarios->contexto($this->tokens[$m[1]], $solicitudId);
    }
}
