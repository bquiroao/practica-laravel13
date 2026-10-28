<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\Puertos;

/** Parte del contrato de FuenteAlergias: la única forma permitida de decir «no pude verificar». */
final class FuenteAlergiasNoDisponible extends \RuntimeException
{
}
