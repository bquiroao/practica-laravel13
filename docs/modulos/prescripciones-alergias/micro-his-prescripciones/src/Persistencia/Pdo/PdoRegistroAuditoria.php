<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\Pdo;

use Prescripciones\Aplicacion\Puertos\RegistroAuditoria;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;

/**
 * Bandeja local (outbox): la traza se escribe en la MISMA transacción que la prescripción y un proceso aparte
 * la envía al módulo Auditoría (semana 5). Así no puede existir una prescripción sin su traza, ni al revés.
 */
final class PdoRegistroAuditoria implements RegistroAuditoria
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function registrar(string $accion, string $hospital, string $usuario, string $recurso, string $resultado): void
    {
        try {
            $this->pdo->prepare('INSERT INTO prescription_audit_outbox (action, hospital, actor, resource, result)
                VALUES (:accion, :hospital, :actor, :recurso, :resultado)')
                ->execute(['accion' => $accion, 'hospital' => $hospital, 'actor' => $usuario, 'recurso' => $recurso, 'resultado' => $resultado]);
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo registrar la traza.', 0, $e);
        }
    }
}
