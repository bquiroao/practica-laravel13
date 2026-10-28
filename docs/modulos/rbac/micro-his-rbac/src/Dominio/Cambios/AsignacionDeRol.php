<?php

declare(strict_types=1);

namespace Rbac\Dominio\Cambios;

use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Dominio\Modelo\SolicitudAsignacion;
use Rbac\Dominio\Puertos\RepositorioMembresias;

/** Alta de una membresía activa + evento ROL_ASIGNADO. */
final class AsignacionDeRol implements CambioDeMembresia
{
    public function __construct(
        private readonly SolicitudAsignacion $solicitud,
        private readonly string $correlationId,
        private readonly string $ip,
    ) {
    }

    public function aplicarEn(RepositorioMembresias $membresias): int
    {
        $membresias->guardar(new Membresia(
            $this->solicitud->usuarioDestinoId,
            $this->solicitud->hospitalId,
            $this->solicitud->rol,
            'active',
            $this->solicitud->validoDesde,
            $this->solicitud->validoHasta,
        ));

        return 1;
    }

    public function evento(int $afectadas): EventoAuditoria
    {
        return EventoAuditoria::rolAsignado($this->solicitud, $this->correlationId, $this->ip);
    }
}
