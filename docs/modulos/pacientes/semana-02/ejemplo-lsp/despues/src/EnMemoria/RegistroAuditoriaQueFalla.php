<?php

declare(strict_types=1);

namespace Pacientes\EnMemoria;

use Pacientes\Contratos\RegistroAuditoria;
use Pacientes\Dominio\EventoAuditoria;

/** Doble de prueba: simula la bitácora caída. */
final class RegistroAuditoriaQueFalla implements RegistroAuditoria
{
    public function registrar(EventoAuditoria $evento): void
    {
        throw new \RuntimeException('Bitácora no disponible');
    }
}
