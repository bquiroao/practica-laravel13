<?php
declare(strict_types=1);

namespace Admision\Domain\Modelo;

enum OrigenAdmision: string
{
    case EMERGENCIA = 'EMERGENCIA';
    case CONSULTA_EXTERNA = 'CONSULTA_EXTERNA';
    case REFERENCIA = 'REFERENCIA';
}
