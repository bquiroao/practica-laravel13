<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\Configuracion;

use Prescripciones\Aplicacion\Puertos\PoliticaAcceso;

/** Provisional: en el HIS la decisión la toma RBAC detrás del mismo puerto. Clave «usuario|hospital». */
final class PoliticaAccesoEstatica implements PoliticaAcceso
{
    /** @param array<string, list<string>> $permisos */
    public function __construct(private readonly array $permisos)
    {
    }

    public function permite(string $usuario, string $permiso, string $hospital): bool
    {
        return in_array($permiso, $this->permisos["$usuario|$hospital"] ?? [], true);
    }
}
