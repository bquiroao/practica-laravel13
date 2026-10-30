<?php
declare(strict_types=1);

namespace Alergias\Presentacion\Api;

final class Identidad
{
    public function __construct(
        public readonly string $usuarioId,
        public readonly string $rol,
        public readonly string $hospital,
    ) {
    }
}
