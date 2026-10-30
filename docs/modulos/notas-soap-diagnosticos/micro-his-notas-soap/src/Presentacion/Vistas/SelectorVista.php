<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Vistas;

use NotasSoap\Presentacion\Http\Solicitud;

/** Elige la vista según la cabecera Accept. El mismo controlador y el mismo modelo sirven a las dos. */
final class SelectorVista
{
    public function __construct(private readonly Vista $json, private readonly Vista $html)
    {
    }

    public function para(Solicitud $s): Vista
    {
        return $s->aceptaHtml() ? $this->html : $this->json;
    }
}
