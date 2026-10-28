<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Comandos;

/** $hospital = PoliticaAcceso::RED pide el alcance de red (CU-07). */
final class ComandoConsultar
{
    /** @param array<string, string> $parametros filtros y, opcionalmente, «cursor» */
    public function __construct(
        public readonly string $actor,
        public readonly string $hospital,
        public readonly array $parametros,
    ) {
    }
}
