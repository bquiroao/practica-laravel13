<?php

declare(strict_types=1);

namespace E2e\Persistencia\Pdo;

use E2e\Dominio\Excepciones\ErrorPersistencia;

final class FabricaConexion
{
    public static function crear(string $dsn, ?string $usuario, ?string $clave): \PDO
    {
        try {
            $pdo = new \PDO($dsn, $usuario, $clave, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET search_path TO e2e; SET TIME ZONE 'UTC'");

            return $pdo;
        } catch (\PDOException $e) {
            // Ni el DSN ni la clave salen en el mensaje: pueden terminar en un log.
            throw new ErrorPersistencia('No se pudo conectar a la base del módulo E2E.', 0, $e);
        }
    }
}
