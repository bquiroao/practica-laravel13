<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Puertos;

/** La decisión de permisos es del módulo RBAC; este puerto solo la pide (CU-05). */
interface PoliticaAcceso
{
    public const RED = '*';

    /** $hospital = PoliticaAcceso::RED para el alcance de red. */
    public function permite(string $actor, ?string $hospital, string $permiso): bool;
}
