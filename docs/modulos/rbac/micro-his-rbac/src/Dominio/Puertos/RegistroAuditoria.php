<?php

declare(strict_types=1);

namespace Rbac\Dominio\Puertos;

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Modelo\EventoAuditoria;

/** Bitácora append-only: solo se agregan y se consultan eventos, nunca se editan ni se borran. */
interface RegistroAuditoria
{
    /** @throws ErrorPersistencia */
    public function registrar(EventoAuditoria $evento): void;

    /** @throws ErrorPersistencia */
    public function buscarPorCorrelacion(string $correlationId): ?EventoAuditoria;
}
