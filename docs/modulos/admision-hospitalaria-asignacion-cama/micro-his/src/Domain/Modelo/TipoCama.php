<?php
declare(strict_types=1);

namespace Admision\Domain\Modelo;

enum TipoCama: string
{
    case GENERAL = 'GENERAL';
    case UCI = 'UCI';
    case PEDIATRICA = 'PEDIATRICA';
}
