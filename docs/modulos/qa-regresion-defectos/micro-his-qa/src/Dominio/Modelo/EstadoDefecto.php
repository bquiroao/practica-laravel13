<?php

declare(strict_types=1);

namespace Qa\Dominio\Modelo;

/** Ciclo de vida del defecto (R-08). */
enum EstadoDefecto: string
{
    case NUEVO = 'NUEVO';
    case ABIERTO = 'ABIERTO';
    case CORREGIDO = 'CORREGIDO';
    case CERRADO = 'CERRADO';
    case REABIERTO = 'REABIERTO';
}
