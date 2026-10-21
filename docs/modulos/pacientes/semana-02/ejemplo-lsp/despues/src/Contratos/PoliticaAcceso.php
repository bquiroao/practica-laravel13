<?php

declare(strict_types=1);

namespace Pacientes\Contratos;

/** Puerto hacia el módulo RBAC (R-05): ¿el actor tiene este permiso en este hospital? */
interface PoliticaAcceso
{
    public function permite(string $actor, string $hospital, string $permiso): bool;
}
