<?php

declare(strict_types=1);

namespace Qa\Cliente;

/** No hubo respuesta. Ojo: el servidor PUDO haber procesado la petición (la respuesta se perdió). */
final class FallaTransporte extends \RuntimeException
{
}
