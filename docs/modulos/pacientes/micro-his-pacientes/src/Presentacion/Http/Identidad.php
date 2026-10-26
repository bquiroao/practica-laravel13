<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http;

/** Quién llama y para qué hospital fue emitido su token. */
final class Identidad
{
    public function __construct(
        public readonly string $actor,
        public readonly string $hospital,
    ) {
    }
}
