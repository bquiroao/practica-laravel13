<?php
declare(strict_types=1);

namespace AlertasCriticas\Persistence;

use AlertasCriticas\Domain\DirectorioDestinatarios;

/** Destinatarios por tenant → servicio → nivel, configurados en el Composition Root (datos ficticios). */
final class DirectorioDestinatariosEnMemoria implements DirectorioDestinatarios
{
    /** @param array<string, array<string, array<int, string[]>>> $configuracion */
    public function __construct(private array $configuracion) {}

    public function destinatarios(string $tenant, string $servicio, int $nivel): array
    {
        return $this->configuracion[$tenant][$servicio][$nivel] ?? [];
    }
}
