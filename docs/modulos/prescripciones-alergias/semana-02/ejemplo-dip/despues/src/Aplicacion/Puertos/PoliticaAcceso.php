<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\Puertos;

/** Lo que el caso de uso necesita de RBAC (R-09): una decisión, no una lista de roles. */
interface PoliticaAcceso
{
    public function permite(string $usuario, string $permiso, string $hospital): bool;
}
