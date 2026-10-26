<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Comandos;

final class ComandoVerificar
{
    /** @param string|null $hospitalVerificado la cadena a verificar (null = la de nivel red) */
    public function __construct(
        public readonly string $actor,
        public readonly ?string $hospitalVerificado,
    ) {
    }
}
