<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\EnMemoria;

use NotasSoap\Aplicacion\Puertos\BandejaSalida;
use NotasSoap\Dominio\Eventos\EventoClinico;

final class BandejaSalidaEnMemoria implements BandejaSalida, Restaurable
{
    /** @var list<EventoClinico> */
    private array $pendientes = [];

    public function agregar(EventoClinico $evento): void
    {
        $this->pendientes[] = $evento;
    }

    /** @return list<EventoClinico> */
    public function pendientes(): array
    {
        return $this->pendientes;
    }

    public function instantanea(): array
    {
        return $this->pendientes;
    }

    public function restaurar(array $estado): void
    {
        $this->pendientes = $estado;
    }
}
