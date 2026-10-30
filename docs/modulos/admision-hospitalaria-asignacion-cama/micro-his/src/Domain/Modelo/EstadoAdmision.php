<?php
declare(strict_types=1);

namespace Admision\Domain\Modelo;

enum EstadoAdmision: string
{
    case ACTIVA = 'ACTIVA';
    case CERRADA = 'CERRADA';
}
