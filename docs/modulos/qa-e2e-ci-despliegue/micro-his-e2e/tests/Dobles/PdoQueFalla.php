<?php

declare(strict_types=1);

/**
 * Doble de PDO que simula la base caída: toda operación lanza PDOException con un mensaje que incluye datos sensibles,
 * para comprobar que los adaptadores no los dejan salir.
 */
final class PdoQueFalla extends \PDO
{
    public const SECRETO = 'host=10.0.0.5 password=s3cr3t';

    public function __construct()
    {
        // No se llama al constructor de PDO: no hay conexión.
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        throw new \PDOException('SQLSTATE[08006] conexión perdida ' . self::SECRETO);
    }

    public function beginTransaction(): bool
    {
        throw new \PDOException('SQLSTATE[08006] conexión perdida ' . self::SECRETO);
    }

    public function inTransaction(): bool
    {
        return false;
    }
}
