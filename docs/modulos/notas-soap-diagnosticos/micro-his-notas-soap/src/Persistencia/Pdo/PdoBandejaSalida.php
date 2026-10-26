<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\Pdo;

use NotasSoap\Aplicacion\Puertos\BandejaSalida;
use NotasSoap\Dominio\Eventos\EventoClinico;

/** outbox_events en la base HOSPITAL; published_at queda vacío hasta que el publicador lo envía a CENTRAL. */
final class PdoBandejaSalida extends RepositorioPdo implements BandejaSalida
{
    public function agregar(EventoClinico $evento): void
    {
        $this->ejecutar(
            'INSERT INTO outbox_events (event_id, event_type, aggregate_id, payload_minimal, occurred_at)
             VALUES (:id, :tipo, :agregado, :datos, :ocurrido)',
            [
                'id' => $evento->id,
                'tipo' => $evento->tipo,
                'agregado' => $evento->agregadoId,
                'datos' => (string) json_encode($evento->datos, JSON_UNESCAPED_UNICODE),
                'ocurrido' => self::fecha($evento->ocurridoEn),
            ],
        );
    }
}
