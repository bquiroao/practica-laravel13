<?php

declare(strict_types=1);

namespace Rbac\Persistencia\Pdo;

use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Puertos\RegistroAuditoria;

/** Bitácora en PostgreSQL; un trigger impide UPDATE y DELETE (ver database/schema.sql). */
final class PdoRegistroAuditoria extends RepositorioPdo implements RegistroAuditoria
{
    public function registrar(EventoAuditoria $evento): void
    {
        $this->ejecutar(
            'INSERT INTO eventos_auditoria (correlation_id, tipo, actor_id, hospital_id, datos, ocurrido_en)
             VALUES (:correlacion, :tipo, :actor, :hospital, CAST(:datos AS jsonb), :ocurrido)',
            [
                'correlacion' => $evento->correlationId,
                'tipo' => $evento->tipo,
                'actor' => $evento->actorId,
                'hospital' => $evento->hospitalId,
                'datos' => json_encode($evento->datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'ocurrido' => $evento->ocurridoEn->format(DATE_ATOM),
            ],
        );
    }

    public function buscarPorCorrelacion(string $correlationId): ?EventoAuditoria
    {
        $fila = $this->ejecutar(
            'SELECT correlation_id, tipo, actor_id, hospital_id, datos, ocurrido_en
               FROM eventos_auditoria
              WHERE correlation_id = :correlacion',
            ['correlacion' => $correlationId],
        )->fetch(\PDO::FETCH_ASSOC);

        if ($fila === false) {
            return null;
        }

        return new EventoAuditoria(
            (string) $fila['tipo'],
            (string) $fila['correlation_id'],
            (string) $fila['actor_id'],
            (string) $fila['hospital_id'],
            json_decode((string) $fila['datos'], true, 512, JSON_THROW_ON_ERROR),
            new \DateTimeImmutable((string) $fila['ocurrido_en']),
        );
    }
}
