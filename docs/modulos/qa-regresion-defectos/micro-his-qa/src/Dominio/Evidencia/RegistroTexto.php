<?php

declare(strict_types=1);

namespace Qa\Dominio\Evidencia;

/** Registro de texto (log, salida de consola): UTF-8 válido y sin bytes nulos. */
final class RegistroTexto extends EvidenciaInmutable
{
    public function __construct(string $texto, Clasificacion $clasificacion = Clasificacion::GENERAL)
    {
        if (!mb_check_encoding($texto, 'UTF-8') || str_contains($texto, "\0")) {
            throw new EvidenciaInvalida('TIPO_NO_PERMITIDO');
        }
        parent::__construct($texto, $clasificacion);
    }

    public function tipo(): string
    {
        return 'registro';
    }
}
