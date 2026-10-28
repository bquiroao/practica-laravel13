<?php

declare(strict_types=1);

namespace Auditoria\Pruebas\Dobles;

/**
 * Doble de PDO con guion: cada prepare() consume el siguiente paso, comprueba que el SQL
 * sea el esperado y devuelve una sentencia que responde las filas indicadas o lanza el
 * SQLSTATE indicado al ejecutarse. Registra cada SQL con sus parámetros, y las llamadas
 * de transacción (BEGIN, COMMIT, ROLLBACK).
 */
final class PdoGuionado extends \PDO
{
    /** @var list<array{sql: string, parametros: array<string, mixed>}> */
    public array $ejecutadas = [];
    /** @var list<string> */
    public array $transaccion = [];
    public bool $fallarCommit = false;
    private bool $abierta = false;

    /**
     * @param list<array{patron?: string, filas?: list<array<string, mixed>>, error?: string}> $guion
     */
    public function __construct(private array $guion = [])
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $paso = array_shift($this->guion) ?? ['filas' => []];
        if (isset($paso['patron']) && preg_match($paso['patron'], $query) !== 1) {
            throw new \RuntimeException('SQL inesperado para ' . $paso['patron'] . ': ' . $query);
        }

        return new SentenciaGuionada($this, $query, $paso['filas'] ?? [], $paso['error'] ?? null);
    }

    public function beginTransaction(): bool
    {
        $this->transaccion[] = 'BEGIN';
        $this->abierta = true;

        return true;
    }

    public function commit(): bool
    {
        if ($this->fallarCommit) {
            throw PdoQueFalla::excepcion('08006', 'SQLSTATE[08006]: conexión perdida en COMMIT');
        }
        $this->transaccion[] = 'COMMIT';
        $this->abierta = false;

        return true;
    }

    public function rollBack(): bool
    {
        $this->transaccion[] = 'ROLLBACK';
        $this->abierta = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->abierta;
    }
}
