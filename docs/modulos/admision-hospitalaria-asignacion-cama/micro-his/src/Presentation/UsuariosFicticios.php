<?php
declare(strict_types=1);

namespace Admision\Presentation;

use Admision\Application\ContextoUsuario;
use Admision\Application\Excepciones\AccesoDenegado;
use Admision\Domain\Modelo\Rol;
use Admision\Domain\Modelo\TenantId;

/** Autenticación FICTICIA para el Micro-HIS: usuario conocido -> rol y hospital. No es un mecanismo de producción. */
final class UsuariosFicticios
{
    /** @param array<string, array{string, string}> $usuarios usuario => [rol, hospital] */
    public function __construct(private readonly array $usuarios)
    {
    }

    public function contexto(string $usuarioId, string $solicitudId): ContextoUsuario
    {
        if (!isset($this->usuarios[$usuarioId])) {
            throw new AccesoDenegado();
        }
        [$rol, $hospital] = $this->usuarios[$usuarioId];
        return new ContextoUsuario($usuarioId, Rol::from($rol), TenantId::desde($hospital), $solicitudId);
    }
}
