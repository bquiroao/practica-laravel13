<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Modelo;

/** Valores de diagnoses.type en la base HOSPITAL. */
enum TipoDiagnostico: string
{
    case Principal = 'primary';
    case Secundario = 'secondary';
    case Diferencial = 'differential';
}
