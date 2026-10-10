<?php

declare(strict_types=1);

namespace Rbac\Contratos;

use Rbac\Dominio\EventoAuditoria;

/** Bitácora append-only: solo admite agregar eventos. */
interface RegistroAuditoria
{
    /** @throws \RuntimeException si el evento no pudo persistirse. */
    public function registrar(EventoAuditoria $evento): void;
}
