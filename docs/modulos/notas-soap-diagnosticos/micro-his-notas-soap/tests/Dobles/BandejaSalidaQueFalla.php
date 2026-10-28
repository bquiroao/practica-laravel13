<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Aplicacion\Puertos\BandejaSalida;
use NotasSoap\Dominio\Eventos\EventoClinico;
use NotasSoap\Dominio\Excepciones\ErrorPersistencia;

/** Doble: la bandeja de salida falla; la firma debe revertirse con ella. */
final class BandejaSalidaQueFalla implements BandejaSalida
{
    public function agregar(EventoClinico $evento): void
    {
        throw new ErrorPersistencia('Error de base de datos.', '08006');
    }
}
