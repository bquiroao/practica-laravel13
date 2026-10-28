<?php

declare(strict_types=1);

namespace Rbac\Dominio\Cambios;

use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Modelo\SolicitudRevocacion;
use Rbac\Dominio\Puertos\RepositorioMembresias;

/** Revocación de las membresías activas y vigentes del rol + evento ROL_REVOCADO. */
final class RevocacionDeRol implements CambioDeMembresia
{
    public function __construct(
        private readonly SolicitudRevocacion $solicitud,
        private readonly string $correlationId,
        private readonly string $ip,
    ) {
    }

    public function aplicarEn(RepositorioMembresias $membresias): int
    {
        return $membresias->revocar(
            $this->solicitud->usuarioDestinoId,
            $this->solicitud->hospitalId,
            $this->solicitud->rol,
            $this->solicitud->ahora,
        );
    }

    public function evento(int $afectadas): EventoAuditoria
    {
        return EventoAuditoria::rolRevocado($this->solicitud, $afectadas, $this->correlationId, $this->ip);
    }
}
