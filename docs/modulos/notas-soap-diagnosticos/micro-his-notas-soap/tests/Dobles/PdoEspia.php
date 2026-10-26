<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

/** Doble de PDO que registra BEGIN / COMMIT / ROLLBACK para probar PdoTransaccion. */
final class PdoEspia extends \PDO
{
    /** @var list<string> */
    public array $llamadas = [];
    public bool $fallarCommit = false;

    public function __construct()
    {
    }

    public function beginTransaction(): bool
    {
        $this->llamadas[] = 'BEGIN';

        return true;
    }

    public function commit(): bool
    {
        if ($this->fallarCommit) {
            $e = new \PDOException('SQLSTATE[40001]: serialization failure');
            $e->errorInfo = ['40001', 7, 'serialization failure'];
            throw $e;
        }
        $this->llamadas[] = 'COMMIT';

        return true;
    }

    public function rollBack(): bool
    {
        $this->llamadas[] = 'ROLLBACK';

        return true;
    }
}
