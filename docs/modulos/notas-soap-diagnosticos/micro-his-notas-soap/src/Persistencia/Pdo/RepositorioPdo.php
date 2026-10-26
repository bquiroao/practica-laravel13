<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\Pdo;

use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use PDO;
use PDOStatement;

/** Base de los adaptadores: toda consulta pasa por una sentencia preparada y ninguna PDOException sale de aquí. */
abstract class RepositorioPdo
{
    public function __construct(protected readonly PDO $pdo)
    {
    }

    /** @param array<string, scalar|null> $parametros */
    protected function ejecutar(string $sql, array $parametros = []): PDOStatement
    {
        try {
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($parametros);

            return $sentencia;
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('Error de base de datos.', self::sqlState($e), $e);
        }
    }

    public static function sqlState(\PDOException $e): ?string
    {
        $estado = $e->errorInfo[0] ?? $e->getCode();

        return is_string($estado) && $estado !== '' ? $estado : null;
    }

    protected static function fecha(\DateTimeImmutable $fecha): string
    {
        return $fecha->format('Y-m-d\TH:i:sP');
    }
}
