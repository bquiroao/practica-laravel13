<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

/**
 * Doble de PDO: cada prepare() lanza una PDOException con el SQLSTATE indicado, como lo haría
 * pdo_pgsql ante una caída de conexión (08006). Prueba los adaptadores sin base de datos.
 */
final class PdoQueFalla extends \PDO
{
    /** @var list<string> */
    public array $sentencias = [];

    public function __construct(private readonly string $sqlState = '08006')
    {
        // No llama al constructor de PDO: no abre ninguna conexión.
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->sentencias[] = $query;
        $e = new \PDOException('SQLSTATE[' . $this->sqlState . ']: error simulado');
        (new \ReflectionProperty(\Exception::class, 'code'))->setValue($e, $this->sqlState);
        $e->errorInfo = [$this->sqlState, 7, 'error simulado'];
        throw $e;
    }
}
