<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Excepciones;

/** Carrera de idempotencia: otra emisión guardó primero la misma clave (R-11). */
final class ClaveEnUso extends \RuntimeException
{
}
