<?php

declare(strict_types=1);

namespace NotasSoap\Cliente;

/** No hubo respuesta HTTP: conexión rechazada, tiempo agotado o respuesta perdida en la red. */
final class FallaTransporte extends \RuntimeException
{
}
