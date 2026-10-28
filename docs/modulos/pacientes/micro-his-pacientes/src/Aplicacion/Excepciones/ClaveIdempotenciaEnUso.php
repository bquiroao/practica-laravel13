<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\Excepciones;

use Pacientes\Dominio\Excepciones\ErrorDominio;

/** Otra petición con la misma Idempotency-Key se está procesando (o ganó la carrera). */
final class ClaveIdempotenciaEnUso extends ErrorDominio
{
    public function __construct()
    {
        parent::__construct(self::CONFLICTO, 'SOLICITUD_EN_CURSO');
    }
}
