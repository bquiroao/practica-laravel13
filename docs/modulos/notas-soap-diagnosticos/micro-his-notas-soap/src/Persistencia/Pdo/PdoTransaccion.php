<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\Pdo;

use NotasSoap\Aplicacion\Puertos\Transaccion;
use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use PDO;

/** BEGIN / COMMIT / ROLLBACK con PDO. */
final class PdoTransaccion implements Transaccion
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ejecutar(callable $trabajo): mixed
    {
        try {
            $this->pdo->beginTransaction();
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo iniciar la transacción.', RepositorioPdo::sqlState($e), $e);
        }

        try {
            $resultado = $trabajo();
            $this->pdo->commit();

            return $resultado;
        } catch (\Throwable $e) {
            try {
                $this->pdo->rollBack();
            } catch (\PDOException) {
                // la conexión ya no está; la base revierte sola al cerrarse
            }
            if ($e instanceof \PDOException) {
                throw new ErrorPersistencia('No se pudo confirmar la transacción.', RepositorioPdo::sqlState($e), $e);
            }
            throw $e;
        }
    }
}
