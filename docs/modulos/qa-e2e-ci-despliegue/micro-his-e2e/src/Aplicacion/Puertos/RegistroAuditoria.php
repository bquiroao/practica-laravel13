<?php

declare(strict_types=1);

namespace E2e\Aplicacion\Puertos;

/** R-14: una línea por operación, sin contenido de evidencias. */
interface RegistroAuditoria
{
    public function registrar(string $accion, string $tenant, string $usuario, string $recurso): void;
}
