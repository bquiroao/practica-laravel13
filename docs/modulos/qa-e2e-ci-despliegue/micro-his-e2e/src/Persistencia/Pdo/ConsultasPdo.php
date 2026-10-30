<?php

declare(strict_types=1);

namespace E2e\Persistencia\Pdo;

use E2e\Dominio\Excepciones\ErrorPersistencia;

/**
 * Lo común de los adaptadores PDO: SIEMPRE sentencias preparadas con parámetros, y ningún PDOException hacia afuera
 * (se traduce a ErrorPersistencia sin el SQL ni el mensaje del driver).
 */
abstract class ConsultasPdo
{
    public function __construct(protected readonly \PDO $pdo)
    {
    }

    /** @param list<mixed> $parametros */
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

    /** Un adaptador puede reconocer una violación concreta (p. ej. la clave de idempotencia) antes de esto. */
    protected function traducir(\PDOException $e): \Exception
    {
        return new ErrorPersistencia('La base del módulo E2E no respondió como se esperaba.', 0, $e);
    }

    /** @return array<mixed> */
    protected static function json(?string $texto): array
    {
        $valor = json_decode($texto ?? '[]', true);

        return is_array($valor) ? $valor : [];
    }

    protected static function aJson(mixed $valor): string
    {
        return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    protected static function hora(string $texto): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($texto))->setTimezone(new \DateTimeZone('UTC'));
    }
}
