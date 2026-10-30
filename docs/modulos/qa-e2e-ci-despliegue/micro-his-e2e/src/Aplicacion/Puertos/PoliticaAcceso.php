<?php

declare(strict_types=1);

namespace E2e\Aplicacion\Puertos;

/** R-13: lo resuelve RBAC; aquí solo la pregunta. */
interface PoliticaAcceso
{
    public function permite(string $usuario, string $permiso, string $tenant): bool;
}
