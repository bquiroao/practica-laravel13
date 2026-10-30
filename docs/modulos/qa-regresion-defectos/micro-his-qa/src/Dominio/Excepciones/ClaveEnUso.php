<?php

declare(strict_types=1);

namespace Qa\Dominio\Excepciones;

/** Otra petición ya registró un intento con esta clave de idempotencia (la base lo detecta con una restricción UNIQUE). */
final class ClaveEnUso extends \RuntimeException
{
}
