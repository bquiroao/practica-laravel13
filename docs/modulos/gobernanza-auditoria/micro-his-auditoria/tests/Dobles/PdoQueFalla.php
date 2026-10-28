<?php

declare(strict_types=1);

namespace Auditoria\Pruebas\Dobles;

/**
 * Doble de PDO: cada prepare() lanza una PDOException con el SQLSTATE indicado, como lo
 * haría pdo_pgsql ante una caída de conexión (08006). Sin base de datos.
 */
final class PdoQueFalla extends \PDO
{
    /** @var list<string> */
    public array $sentencias = [];

    /** No llama al constructor de PDO: no abre ninguna conexión real. */
    public function __construct(private readonly string $sqlState = '08006')
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->sentencias[] = $query;

        throw self::excepcion($this->sqlState, 'SQLSTATE[' . $this->sqlState . ']: error simulado');
    }

    public static function excepcion(string $sqlState, string $mensaje): \PDOException
    {
        $e = new \PDOException($mensaje);
        (new \ReflectionProperty(\Exception::class, 'code'))->setValue($e, $sqlState);
        $e->errorInfo = [$sqlState, 7, $mensaje];

        return $e;
    }
}
