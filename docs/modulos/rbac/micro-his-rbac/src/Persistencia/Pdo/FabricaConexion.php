<?php

declare(strict_types=1);

namespace Rbac\Persistencia\Pdo;

use Rbac\Dominio\Excepciones\ErrorPersistencia;

final class FabricaConexion
{
    /** @throws ErrorPersistencia */
    public static function crear(string $dsn, string $usuario, string $clave): \PDO
    {
        try {
            return new \PDO($dsn, $usuario, $clave, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                // Sentencias preparadas reales en el servidor, no emuladas por PHP.
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo conectar a la base de datos', null, $e);
        }
    }
}
