<?php

declare(strict_types=1);

namespace Auditoria\Persistencia\Pdo;

use Auditoria\Dominio\Excepciones\ErrorPersistencia;

/** Conexión PDO con las opciones que exige el módulo. Nunca expone la contraseña en el error. */
final class FabricaConexion
{
    /** @throws ErrorPersistencia */
    public static function crear(string $dsn, string $usuario, string $clave): \PDO
    {
        try {
            $pdo = new \PDO($dsn, $usuario, $clave, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,           // sentencias preparadas reales en el servidor
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            // Las fechas se leen y se escriben en UTC (R-09); la hora local solo existe en los filtros.
            $pdo->prepare("SET TIME ZONE 'UTC'")->execute();

            return $pdo;
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo conectar con la base de auditoría.', (string) $e->getCode());
        }
    }
}
