<?php

declare(strict_types=1);

namespace Rbac\Pruebas\Soporte;

use Rbac\Persistencia\Pdo\FabricaConexion;

/**
 * Base de prueba en PostgreSQL: crea un esquema temporal con schema.sql + datos-demo.sql,
 * entrega la conexión y lo elimina al terminar. Requiere MICROHIS_DB_DSN.
 */
final class PostgresTemporal
{
    public static function disponible(): bool
    {
        $dsn = getenv('MICROHIS_DB_DSN');

        return $dsn !== false && $dsn !== '';
    }

    /** @param callable(\PDO): void $trabajo */
    public static function ejecutar(callable $trabajo): void
    {
        $pdo = FabricaConexion::crear(
            (string) getenv('MICROHIS_DB_DSN'),
            getenv('MICROHIS_DB_USUARIO') ?: 'postgres',
            getenv('MICROHIS_DB_CLAVE') ?: '',
        );
        $esquema = 'microhis_prueba_' . bin2hex(random_bytes(4));
        $raiz = dirname(__DIR__, 2);

        $pdo->exec("CREATE SCHEMA $esquema");
        try {
            $pdo->exec("SET search_path TO $esquema");
            $pdo->exec((string) file_get_contents($raiz . '/database/schema.sql'));
            $pdo->exec((string) file_get_contents($raiz . '/database/datos-demo.sql'));
            $trabajo($pdo);
        } finally {
            $pdo->exec('SET search_path TO public');
            $pdo->exec("DROP SCHEMA $esquema CASCADE");
        }
    }
}
