<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Puertos;

/** Genera identificadores (UUID) desde la aplicación, igual en PostgreSQL y en las pruebas. */
interface GeneradorId
{
    public function nuevo(): string;
}
