<?php
declare(strict_types=1);

namespace Admision\Application\Excepciones;

/** RN-06: el rol del usuario no permite la operación. */
final class AccesoDenegado extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Su rol no permite esta operación.');
    }

    public function codigo(): string
    {
        return 'ACCESO_DENEGADO';
    }
}
