<?php

declare(strict_types=1);

namespace Pacientes\Contratos;

use Pacientes\Dominio\EventoAuditoria;

/** Bitácora append-only: solo se agregan eventos. */
interface RegistroAuditoria
{
    public function registrar(EventoAuditoria $evento): void;
}
