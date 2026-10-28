<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Excepciones;

/** Carrera de anulación: otra petición anuló primero la misma prescripción (la base lo impide con su clave primaria). */
final class YaAnulada extends \RuntimeException
{
}
