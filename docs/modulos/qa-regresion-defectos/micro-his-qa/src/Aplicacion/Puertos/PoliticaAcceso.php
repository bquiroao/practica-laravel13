<?php

declare(strict_types=1);

namespace Qa\Aplicacion\Puertos;

/** Lo decide RBAC; aquí solo el contrato. */
interface PoliticaAcceso
{
    public function permite(string $usuario, string $permiso, string $tenant): bool;
}
