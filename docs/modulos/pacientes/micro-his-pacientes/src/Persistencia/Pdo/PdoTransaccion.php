<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\Pdo;

use Pacientes\Aplicacion\Puertos\Transaccion;
use Pacientes\Dominio\Excepciones\ErrorPersistencia;

/** BEGIN / COMMIT / ROLLBACK sobre la misma conexión que usan los repositorios. */
final class PdoTransaccion implements Transaccion
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function ejecutar(\Closure $trabajo): mixed
    {
        try {
            $this->pdo->beginTransaction();
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo iniciar la transacción', null, $e);
        }

        try {
            $resultado = $trabajo();
            $this->pdo->commit();

            return $resultado;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                try {
                    $this->pdo->rollBack();
                } catch (\PDOException) {
                    // La conexión ya no responde; el servidor descartará la transacción.
                }
            }

            throw $e instanceof \PDOException ? new ErrorPersistencia('Error al confirmar la transacción', null, $e) : $e;
        }
    }
}
