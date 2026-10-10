<?php

declare(strict_types=1);

namespace Rbac\Pruebas\Dobles;

/** Doble de PDO que registra las llamadas de transacción; puede hacer fallar el COMMIT. */
final class PdoEspia extends \PDO
{
    /** @var list<string> */
    public array $llamadas = [];
    public bool $fallarCommit = false;
    private bool $enTransaccion = false;

    /** No llama al constructor de PDO: no abre ninguna conexión real. */
    public function __construct()
    {
    }

    public function beginTransaction(): bool
    {
        $this->llamadas[] = 'BEGIN';
        $this->enTransaccion = true;

        return true;
    }

    public function commit(): bool
    {
        if ($this->fallarCommit) {
            throw PdoQueFalla::excepcion('08006', 'SQLSTATE[08006]: conexión perdida en COMMIT');
        }
        $this->llamadas[] = 'COMMIT';
        $this->enTransaccion = false;

        return true;
    }

    public function rollBack(): bool
    {
        $this->llamadas[] = 'ROLLBACK';
        $this->enTransaccion = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->enTransaccion;
    }
}
