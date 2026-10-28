<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Comandos;

final class ComandoAncla
{
    /** @param string|null $hospital la cadena cuya cabeza se pide (null = la de nivel red) */
    public function __construct(
        public readonly string $actor,
        public readonly ?string $hospital,
    ) {
    }
}
