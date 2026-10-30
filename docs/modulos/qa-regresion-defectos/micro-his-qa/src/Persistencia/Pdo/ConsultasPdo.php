<?php

declare(strict_types=1);

namespace Qa\Persistencia\Pdo;

use Qa\Dominio\Excepciones\ErrorPersistencia;

/**
 * Lo común de los adaptadores PDO: SIEMPRE sentencias preparadas con parámetros, y ningún PDOException hacia afuera
 * (se traduce a ErrorPersistencia sin el SQL ni el mensaje del driver).
 */
abstract class ConsultasPdo
{
    public function __construct(protected readonly \PDO $pdo)
    {
    }

    /** @param array<int|string, mixed> $parametros */
    protected function consulta(string $sql, array $parametros = []): \PDOStatement
    {
        try {
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($parametros);

            return $sentencia;
        } catch (\PDOException $e) {
            throw $this->traducir($e);
        }
    }

    /** Puntos de enlace «?, ?, ?» para un IN con una lista (los valores siempre van como parámetros). */
    protected static function enlaces(array $valores): string
    {
        return implode(', ', array_fill(0, max(1, count($valores)), '?'));
    }

    /** Un adaptador puede reconocer una violación concreta (p. ej. la clave de idempotencia) antes de esto. */
    protected function traducir(\PDOException $e): \RuntimeException
    {
        return new ErrorPersistencia('La base del módulo QA no respondió como se esperaba.', 0, $e);
    }

    /** @return list<mixed> */
    protected static function json(?string $texto): array
    {
        $valor = json_decode($texto ?? '[]', true);

        return is_array($valor) ? $valor : [];
    }
}
