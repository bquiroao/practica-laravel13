<?php

declare(strict_types=1);

namespace E2e\Persistencia\Configuracion;

use E2e\Aplicacion\Puertos\PoliticaAcceso;

/** Mientras el módulo RBAC no expone su API: permisos por «usuario|tenant» leídos de la configuración. */
final class PoliticaAccesoEstatica implements PoliticaAcceso
{
    /** @param array<string, list<string>> $permisos */
    public function __construct(private readonly array $permisos)
    {
    }

    public function permite(string $usuario, string $permiso, string $tenant): bool
    {
        return in_array($permiso, $this->permisos["$usuario|$tenant"] ?? [], true);
    }
}
