<?php

declare(strict_types=1);

namespace Rbac\EnMemoria;

use Rbac\Contratos\RegistroAuditoria;
use Rbac\Dominio\EventoAuditoria;

/** Doble de prueba: simula una bitácora caída (E-04, E-07). */
final class RegistroAuditoriaQueFalla implements RegistroAuditoria
{
    public function registrar(EventoAuditoria $evento): void
    {
        throw new \RuntimeException('Bitácora no disponible');
    }
}
