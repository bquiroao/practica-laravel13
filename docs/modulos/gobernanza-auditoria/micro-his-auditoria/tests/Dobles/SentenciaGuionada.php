<?php

declare(strict_types=1);

namespace Auditoria\Pruebas\Dobles;

final class SentenciaGuionada extends \PDOStatement
{
    /** @param list<array<string, mixed>> $filas */
    public function __construct(
        private readonly PdoGuionado $pdo,
        private readonly string $sql,
        private array $filas,
        private readonly ?string $error,
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $this->pdo->ejecutadas[] = ['sql' => $this->sql, 'parametros' => $params ?? []];
        if ($this->error !== null) {
            throw PdoQueFalla::excepcion($this->error, 'SQLSTATE[' . $this->error . ']: error simulado');
        }

        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->filas) ?? false;
    }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $filas = $this->filas;
        $this->filas = [];

        return $mode === \PDO::FETCH_COLUMN ? array_map(static fn (array $f): mixed => reset($f), $filas) : $filas;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $fila = array_shift($this->filas);

        return $fila === null ? false : array_values($fila)[$column];
    }
}
