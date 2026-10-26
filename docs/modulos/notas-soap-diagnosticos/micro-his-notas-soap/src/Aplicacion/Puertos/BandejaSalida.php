<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Puertos;

use NotasSoap\Dominio\Eventos\EventoClinico;

/**
 * Bandeja de salida (outbox): guarda el evento en la misma transacción que el cambio.
 * Un proceso aparte lo publica después hacia el repositorio compartido.
 */
interface BandejaSalida
{
    public function agregar(EventoClinico $evento): void;
}
