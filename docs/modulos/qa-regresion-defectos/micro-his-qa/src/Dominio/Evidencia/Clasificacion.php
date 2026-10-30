<?php

declare(strict_types=1);

namespace Qa\Dominio\Evidencia;

enum Clasificacion: string
{
    case GENERAL = 'GENERAL';
    case DATOS_CLINICOS = 'DATOS_CLINICOS';
}
