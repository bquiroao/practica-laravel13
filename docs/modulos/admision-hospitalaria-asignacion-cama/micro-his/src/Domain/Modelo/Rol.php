<?php
declare(strict_types=1);

namespace Admision\Domain\Modelo;

/** Roles de RN-06. */
enum Rol: string
{
    case ADMISIONISTA = 'ADMISIONISTA';
    case SUPERVISOR_CAMAS = 'SUPERVISOR_CAMAS';
    case MEDICO = 'MEDICO';
}
