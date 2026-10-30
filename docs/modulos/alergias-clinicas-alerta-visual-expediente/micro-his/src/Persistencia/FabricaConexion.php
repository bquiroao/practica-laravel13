<?php
declare(strict_types=1);

namespace Alergias\Persistencia;

use Alergias\Dominio\ErrorPersistencia;
use PDO;
use PDOException;

final class FabricaConexion
{
    /** Datos locales de desarrollo; se cambian con variables de entorno. */
    public static function desdeEntorno(string $base = 'micro_alergias'): PDO
    {
        $dsn = getenv('ALERGIAS_DB_DSN') ?: "pgsql:host=127.0.0.1;port=55442;dbname={$base}";
        return self::crear($dsn, getenv('ALERGIAS_DB_USUARIO') ?: 'postgres', getenv('ALERGIAS_DB_CLAVE') ?: 'postgres');
    }

    public static function crear(string $dsn, string $usuario, string $clave): PDO
    {
        try {
            return new PDO($dsn, $usuario, $clave, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 3,
            ]);
        } catch (PDOException $e) {
            throw new ErrorPersistencia('No hay conexión con la base de datos.', 0, $e);
        }
    }
}
