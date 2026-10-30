<?php

declare(strict_types=1);

namespace Qa\Dominio\Modelo;

/** Estado de una campaña de regresión. */
enum EstadoCampana: string
{
    case PLANIFICADA = 'PLANIFICADA';
    case EN_CURSO = 'EN_CURSO';
    case CERRADA = 'CERRADA';
}
