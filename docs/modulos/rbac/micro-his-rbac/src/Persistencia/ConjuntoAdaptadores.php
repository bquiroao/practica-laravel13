<?php

declare(strict_types=1);

namespace Rbac\Persistencia;

use Rbac\Aplicacion\Puertos\Transaccion;
use Rbac\Dominio\Puertos\CatalogoPermisos;
use Rbac\Dominio\Puertos\DirectorioUsuarios;
use Rbac\Dominio\Puertos\RegistroAuditoria;
use Rbac\Dominio\Puertos\RepositorioMembresias;

/**
 * Las cinco implementaciones de puertos que necesita el módulo, siempre del mismo origen
 * (todas PDO sobre una misma conexión, o todas en memoria) para que la transacción las abarque.
 */
final class ConjuntoAdaptadores
{
    public function __construct(
        public readonly RepositorioMembresias $membresias,
        public readonly CatalogoPermisos $catalogo,
        public readonly DirectorioUsuarios $usuarios,
        public readonly RegistroAuditoria $auditoria,
        public readonly Transaccion $transaccion,
    ) {
    }
}
