<?php
declare(strict_types=1);

namespace MicroHis\Domain;

/** El período no tiene datos: se informa «Sin datos», nunca cero (RN-06). */
final class SinDatos extends \RuntimeException
{
}
