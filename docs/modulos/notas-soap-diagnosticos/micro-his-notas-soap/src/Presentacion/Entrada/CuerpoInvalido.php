<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Entrada;

/** Error de FORMATO de la petición (400). Las reglas de negocio no se revisan aquí: esas dan 422 desde el Dominio. */
final class CuerpoInvalido extends \InvalidArgumentException
{
}
