<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\Comandos;

final class ComandoBuscar
{
    /** @param array<string, ?string> $criterios codigo, tipo_documento, numero_documento, pais_documento, nombre, nacimiento */
    public function __construct(
        public readonly string $actor,
        public readonly string $hospital,
        public readonly array $criterios,
    ) {
    }
}
