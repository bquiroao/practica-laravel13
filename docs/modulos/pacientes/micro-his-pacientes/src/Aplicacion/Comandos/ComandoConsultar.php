<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\Comandos;

final class ComandoConsultar
{
    public function __construct(
        public readonly string $actor,
        public readonly string $hospital,
        public readonly string $codigo,
    ) {
    }
}
