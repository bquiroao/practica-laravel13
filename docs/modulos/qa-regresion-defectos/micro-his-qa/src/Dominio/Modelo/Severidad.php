<?php

declare(strict_types=1);

namespace Qa\Dominio\Modelo;

/** Severidad de un defecto (R-07). */
enum Severidad: string
{
    case CRITICA = 'CRITICA';
    case MAYOR = 'MAYOR';
    case MENOR = 'MENOR';
    case TRIVIAL = 'TRIVIAL';
}
