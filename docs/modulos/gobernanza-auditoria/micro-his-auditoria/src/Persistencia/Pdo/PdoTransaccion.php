<?php

declare(strict_types=1);

namespace Auditoria\Persistencia\Pdo;

use Auditoria\Aplicacion\Puertos\Transaccion;
use Auditoria\Dominio\Excepciones\ErrorPersistencia;

/** BEGIN … COMMIT; ante cualquier excepción, ROLLBACK y se relanza (R-10). */
final class PdoTransaccion implements Transaccion
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function ejecutar(callable $operacion): mixed
    {
        try {
            $this->pdo->beginTransaction();
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo iniciar la transacción.', (string) $e->getCode(), $e);
        }

        try {
            $resultado = $operacion();
            $this->pdo->commit();

            return $resultado;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e instanceof \PDOException ? new ErrorPersistencia('No se pudo confirmar la transacción.', (string) $e->getCode(), $e) : $e;
        }
    }
}
