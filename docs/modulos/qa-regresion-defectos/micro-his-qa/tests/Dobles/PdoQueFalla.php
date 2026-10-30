<?php

declare(strict_types=1);

/**
 * Doble de PDO que simula la base caída: toda operación lanza PDOException con un mensaje que incluye
 * datos sensibles, para comprobar que los adaptadores no los dejan salir.
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

    public function exec(string $statement): int|false
    {
        throw new \PDOException('SQLSTATE[08006] conexión perdida ' . self::SECRETO);
    }

    public function inTransaction(): bool
    {
        return false;
    }
}

/** PDO que confirma el inicio de la transacción pero falla al confirmar (commit), para probar el rollback. */
final class PdoQueFallaAlConfirmar extends \PDO
{
    public bool $revertida = false;
    private bool $abierta = false;

    public function __construct()
    {
    }

    public function beginTransaction(): bool
    {
        return $this->abierta = true;
    }

    public function commit(): bool
    {
        throw new \PDOException('SQLSTATE[40001] could not serialize ' . PdoQueFalla::SECRETO);
    }

    public function rollBack(): bool
    {
        $this->abierta = false;

        return $this->revertida = true;
    }

    public function inTransaction(): bool
    {
        return $this->abierta;
    }
}
