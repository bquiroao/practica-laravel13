<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Vistas;

use NotasSoap\Aplicacion\Resultados\NotaResultado;
use NotasSoap\Presentacion\Http\Respuesta;

/** La V del MVC: decide CÓMO se muestra un resultado. No llama casos de uso ni toca la base. */
interface Vista
{
    public function nota(NotaResultado $nota, int $estado): Respuesta;

    public function error(int $estado, string $codigo, string $mensaje): Respuesta;
}
