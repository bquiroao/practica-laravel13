<?php

declare(strict_types=1);

namespace Auditoria\Contratos;

/** La decisión de permisos es del módulo RBAC; este puerto solo la pide. */
interface PoliticaAcceso
{
    /** $hospital = '*' para el alcance de red; null para un emisor de nivel red. */
    public function permite(string $actor, ?string $hospital, string $permiso): bool;
}
