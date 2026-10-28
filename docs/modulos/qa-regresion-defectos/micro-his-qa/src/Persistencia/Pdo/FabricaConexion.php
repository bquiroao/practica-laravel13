<?php

declare(strict_types=1);

namespace Qa\Persistencia\Pdo;

use Qa\Dominio\Excepciones\ErrorPersistencia;

final class FabricaConexion
{
    /** @throws ErrorPersistencia sin el DSN ni la contraseña en el mensaje */
    public static function crear(string $dsn, ?string $usuario, ?string $clave): \PDO
    {
        try {
            $pdo = new \PDO($dsn, $usuario, $clave, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            // UTC en la sesión: sin esto, timestamptz vuelve con la zona del servidor y la misma hora
            // se ve distinta según el adaptador (hallazgo H-2).
            $pdo->exec("SET search_path TO qa; SET TIME ZONE 'UTC'");

            return $pdo;
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo conectar a la base del módulo QA.', 0, $e);
        }
    }
}
