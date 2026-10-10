<?php

declare(strict_types=1);

namespace Rbac\Persistencia\Pdo;

use Rbac\Persistencia\ConjuntoAdaptadores;

/** Crea los adaptadores PDO compartiendo UNA conexión, para que PdoTransaccion abarque a todos. */
final class FabricaPdo
{
    public static function crear(\PDO $pdo): ConjuntoAdaptadores
    {
        return new ConjuntoAdaptadores(
            new PdoRepositorioMembresias($pdo),
            new PdoCatalogoPermisos($pdo),
            new PdoDirectorioUsuarios($pdo),
            new PdoRegistroAuditoria($pdo),
            new PdoTransaccion($pdo),
        );
    }
}
