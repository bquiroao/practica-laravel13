<?php

declare(strict_types=1);

namespace Pacientes\EnMemoria;

use Pacientes\Contratos\PoliticaAcceso;

/** Doble del módulo RBAC: permisos por actor y hospital. */
final class PoliticaAccesoEnMemoria implements PoliticaAcceso
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
