<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Aplicacion\Puertos\GeneradorId;

final class GeneradorSecuencial implements GeneradorId
{
    private int $n = 0;

    public function nuevo(): string
    {
        return sprintf('id-%03d', ++$this->n);
    }
}
