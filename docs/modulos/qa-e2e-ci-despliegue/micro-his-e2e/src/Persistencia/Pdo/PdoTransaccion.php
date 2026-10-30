<?php

declare(strict_types=1);

namespace E2e\Persistencia\Pdo;

use E2e\Aplicacion\Puertos\Transaccion;
use E2e\Dominio\Excepciones\ErrorPersistencia;

final class PdoTransaccion implements Transaccion
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function ejecutar(callable $trabajo): mixed
    {
        try {
            $this->pdo->beginTransaction();
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo abrir la transacción.', 0, $e);
        }
        try {
            $resultado = $trabajo();
            $this->pdo->commit();

            return $resultado;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e instanceof \PDOException ? new ErrorPersistencia('La transacción no se pudo confirmar.', 0, $e) : $e;
        }
    }
}
