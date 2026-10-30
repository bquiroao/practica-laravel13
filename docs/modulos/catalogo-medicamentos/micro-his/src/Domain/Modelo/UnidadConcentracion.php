<?php
declare(strict_types=1);

namespace Catalogo\Domain\Modelo;

/** Unidades de concentración aceptadas. Cada valor cabe en Limites::UNIDAD_MAX. */
enum UnidadConcentracion: string
{
    case MG = 'mg';
    case G = 'g';
    case MCG = 'mcg';
    case UI = 'UI';
    case ML = 'mL';
    case PORCENTAJE = '%';
    case MG_ML = 'mg/mL';
    case MG_5ML = 'mg/5mL';
}
