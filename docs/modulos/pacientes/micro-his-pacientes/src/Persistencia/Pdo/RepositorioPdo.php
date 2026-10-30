<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\Pdo;

use Pacientes\Dominio\Excepciones\ErrorPersistencia;

/**
 * Base de los adaptadores PDO: toda consulta pasa por una sentencia preparada con
 * parámetros con nombre, y toda PDOException se traduce a ErrorPersistencia.
 */
abstract class RepositorioPdo
{
    public function __construct(protected readonly \PDO $pdo)
    {
    }

    /**
     * @param array<string, string|int|null> $parametros
     * @throws ErrorPersistencia
     */
    protected function ejecutar(string $sql, array $parametros = []): \PDOStatement
    {
        try {
            $sentencia = $this->pdo->prepare($sql);
            if ($sentencia === false) {
                throw new ErrorPersistencia('No se pudo preparar la sentencia');
            }
            $sentencia->execute($parametros);

            return $sentencia;
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('Error de base de datos', self::sqlState($e), $e);
        }
    }

    private static function sqlState(\PDOException $e): ?string
    {
        $estado = $e->errorInfo[0] ?? $e->getCode();

        return is_string($estado) && $estado !== '' ? $estado : null;
    }
}
