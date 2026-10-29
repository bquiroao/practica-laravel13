<?php
declare(strict_types=1);

namespace Admision\Application;

use Admision\Application\Excepciones\AccesoDenegado;
use Admision\Domain\Modelo\Responsable;
use Admision\Domain\Modelo\Rol;
use Admision\Domain\Modelo\TenantId;

/** Usuario autenticado. El hospital (tenant) sale de aquí, nunca de los datos que envía el cliente. */
final class ContextoUsuario
{
    public function __construct(
        public readonly string $usuarioId,
        public readonly Rol $rol,
        public readonly TenantId $tenantId,
        public readonly string $solicitudId,
    ) {
    }

    public function exigirRol(Rol ...$permitidos): void
    {
        if (!in_array($this->rol, $permitidos, true)) {
            throw new AccesoDenegado();
        }
    }

    public function responsable(): Responsable
    {
        return new Responsable($this->usuarioId, $this->rol, $this->solicitudId);
    }
}
