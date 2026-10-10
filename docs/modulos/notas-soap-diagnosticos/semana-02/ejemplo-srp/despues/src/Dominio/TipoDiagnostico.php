<?php

declare(strict_types=1);

namespace NotasSoap\Dominio;

/** Valores que acepta la columna diagnoses.type de la base HOSPITAL. */
enum TipoDiagnostico: string
{
    case Principal = 'primary';
    case Secundario = 'secondary';
    case Diferencial = 'differential';
}
