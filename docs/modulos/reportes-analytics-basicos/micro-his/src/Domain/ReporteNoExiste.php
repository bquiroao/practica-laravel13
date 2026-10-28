<?php
declare(strict_types=1);

namespace MicroHis\Domain;

/** Se pidió un tipo de reporte que no está registrado. */
final class ReporteNoExiste extends \OutOfBoundsException
{
}
