<?php

declare(strict_types=1);

namespace Prescripciones\Adaptadores\EnMemoria;

use Prescripciones\Aplicacion\Puertos\PoliticaAcceso;

final class PoliticaEnMemoria implements PoliticaAcceso
{
    /** @param array<string, array<string, list<string>>> $permisos usuario => hospital => permisos */
    public function __construct(private readonly array $permisos)
    {
    }

    public function permite(string $usuario, string $permiso, string $hospital): bool
    {
        return in_array($permiso, $this->permisos[$usuario][$hospital] ?? [], true);
    }
}
