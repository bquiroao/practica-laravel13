<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Excepciones;

/** R-01: uno o más campos no cumplen las reglas. */
final class DatosInvalidos extends ErrorDominio
{
    /** @param array<string, string> $campos campo => motivo */
    public function __construct(array $campos)
    {
        parent::__construct(self::INVALIDO, 'DATOS_INVALIDOS', ['campos' => $campos]);
    }
}
