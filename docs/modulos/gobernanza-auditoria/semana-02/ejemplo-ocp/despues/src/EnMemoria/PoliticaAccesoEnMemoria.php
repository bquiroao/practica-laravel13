<?php

declare(strict_types=1);

namespace Auditoria\EnMemoria;

use Auditoria\Contratos\PoliticaAcceso;

/** Sustituto de RBAC para el ejemplo: "actor|hospital" => permisos ("actor|*" = red). Fail-closed. */
final class PoliticaAccesoEnMemoria implements PoliticaAcceso
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
