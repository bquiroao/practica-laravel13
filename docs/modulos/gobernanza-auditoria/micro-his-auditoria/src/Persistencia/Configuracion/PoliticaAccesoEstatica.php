<?php

declare(strict_types=1);

namespace Auditoria\Persistencia\Configuracion;

use Auditoria\Aplicacion\Puertos\PoliticaAcceso;

/**
 * Provisional: permisos leídos de la configuración ("actor|hospital" => permisos; "actor|*" = red).
 * En el HIS se reemplaza por un adaptador que pregunta a RBAC. Fail-closed: lo que no está, no se permite.
 */
final class PoliticaAccesoEstatica implements PoliticaAcceso
{
    /** @param array<string, list<string>> $permisos */
    public function __construct(private readonly array $permisos)
    {
    }

    public function permite(string $actor, ?string $hospital, string $permiso): bool
    {
        return in_array($permiso, $this->permisos[$actor . '|' . $hospital] ?? [], true);
    }
}
