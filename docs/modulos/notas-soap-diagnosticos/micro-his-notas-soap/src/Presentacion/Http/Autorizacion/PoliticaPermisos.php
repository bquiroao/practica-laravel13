<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http\Autorizacion;

use NotasSoap\Presentacion\Http\Identidad;

/**
 * Punto de aplicación de permisos por ruta (PEP). En el micro-HIS se resuelve con un mapa rol → permisos;
 * en el HIS se reemplaza por un adaptador que pregunta al punto de decisión del módulo RBAC (ASII-02).
 */
interface PoliticaPermisos
{
    public function permite(Identidad $quien, string $permiso): bool;
}
