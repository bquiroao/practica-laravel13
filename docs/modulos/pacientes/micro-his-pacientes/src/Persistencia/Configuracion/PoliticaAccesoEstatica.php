<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\Configuracion;

use Pacientes\Aplicacion\Puertos\PoliticaAcceso;

/**
 * Permisos leídos de la configuración. Es el adaptador provisional del puerto hacia RBAC:
 * en el HIS se reemplaza por una llamada al PDP del módulo RBAC sin tocar los casos de uso.
 */
final class PoliticaAccesoEstatica implements PoliticaAcceso
{
    /** @param array<string, list<string>> $permisos clave "actor|hospital" */
    public function __construct(private readonly array $permisos)
    {
    }

    public function permite(string $actor, string $hospital, string $permiso): bool
    {
        return in_array($permiso, $this->permisos[$actor . '|' . $hospital] ?? [], true);
    }
}
