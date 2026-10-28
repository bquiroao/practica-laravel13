<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Comandos;

final class ComandoDetalle
{
    public function __construct(
        public readonly string $actor,
        public readonly string $hospital,
        public readonly int $id,
    ) {
    }
}
