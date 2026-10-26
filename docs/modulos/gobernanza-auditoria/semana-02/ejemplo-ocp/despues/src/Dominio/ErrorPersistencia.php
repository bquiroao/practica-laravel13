<?php

declare(strict_types=1);

namespace Auditoria\Dominio;

/** La base de datos no respondió. El servicio lo traduce a «no disponible», sin detalles. */
final class ErrorPersistencia extends \RuntimeException
{
}
