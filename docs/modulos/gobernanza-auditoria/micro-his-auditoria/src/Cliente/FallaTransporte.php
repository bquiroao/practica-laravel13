<?php

declare(strict_types=1);

namespace Auditoria\Cliente;

/** No hubo respuesta HTTP: conexión rechazada, timeout o red caída. */
final class FallaTransporte extends \RuntimeException
{
}
