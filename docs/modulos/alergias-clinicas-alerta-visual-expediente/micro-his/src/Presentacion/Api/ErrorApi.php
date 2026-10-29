<?php
declare(strict_types=1);

namespace Alergias\Presentacion\Api;

use RuntimeException;

/** Errores propios de la entrada HTTP (autenticación, tenant, permiso, formato). */
final class ErrorApi extends RuntimeException
{
    public function __construct(public readonly int $estado, public readonly string $codigo, string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
