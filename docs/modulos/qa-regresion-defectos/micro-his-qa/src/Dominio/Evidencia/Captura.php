<?php

declare(strict_types=1);

namespace Qa\Dominio\Evidencia;

/** Captura de pantalla: PNG o JPEG, reconocidos por su firma. */
final class Captura extends EvidenciaInmutable
{
    public function __construct(string $bytes, Clasificacion $clasificacion = Clasificacion::GENERAL)
    {
        if (!str_starts_with($bytes, "\x89PNG\r\n\x1a\n") && !str_starts_with($bytes, "\xFF\xD8\xFF")) {
            throw new EvidenciaInvalida('TIPO_NO_PERMITIDO');
        }
        parent::__construct($bytes, $clasificacion);
    }

    public function tipo(): string
    {
        return 'captura';
    }
}
