<?php
declare(strict_types=1);

namespace Admision\Domain\Modelo;

enum TipoEvento: string
{
    case ADMISION_CREADA = 'ADMISION_CREADA';
    case CAMA_ASIGNADA = 'CAMA_ASIGNADA';
}
