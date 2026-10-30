<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\Pdo;

use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Dominio\Puertos\RegistroAuditoria;

/** Bitácora en eventos_auditoria (append-only: un trigger rechaza UPDATE y DELETE). */
final class PdoRegistroAuditoria extends RepositorioPdo implements RegistroAuditoria
{
    private const SQL_INSERTAR = 'INSERT INTO eventos_auditoria (accion, actor_id, hospital_id, paciente_codigo, detalle)
        VALUES (:accion, :actor, :hospital, :paciente, CAST(:detalle AS jsonb))';

    public function registrar(EventoAuditoria $evento): void
    {
        try {
            $detalle = json_encode($evento->detalle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            throw new ErrorPersistencia('Detalle de auditoría no serializable', null, $e);
        }

        $this->ejecutar(self::SQL_INSERTAR, [
            'accion' => $evento->accion,
            'actor' => $evento->actor,
            'hospital' => $evento->hospital,
            'paciente' => $evento->paciente,
            'detalle' => $detalle,
        ]);
    }
}
