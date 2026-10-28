<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Puertos;

use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Modelo\EventoAuditoria;

/** Bitácora append-only: solo se agregan eventos. */
interface RegistroAuditoria
{
    /** @throws ErrorPersistencia */
    public function registrar(EventoAuditoria $evento): void;
}
