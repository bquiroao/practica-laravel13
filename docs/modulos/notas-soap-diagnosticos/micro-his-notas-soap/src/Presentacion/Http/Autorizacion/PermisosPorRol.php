<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http\Autorizacion;

use NotasSoap\Presentacion\Http\Identidad;

final class PermisosPorRol implements PoliticaPermisos
{
    /** @param array<string, list<string>> $mapa rol => permisos */
    public function __construct(private readonly array $mapa)
    {
    }

    public function permite(Identidad $quien, string $permiso): bool
    {
        foreach ($quien->roles as $rol) {
            if (in_array($permiso, $this->mapa[$rol] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
}
