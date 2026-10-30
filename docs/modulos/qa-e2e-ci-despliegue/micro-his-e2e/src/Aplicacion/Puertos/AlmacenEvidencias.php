<?php

declare(strict_types=1);

namespace E2e\Aplicacion\Puertos;

use E2e\Dominio\Modelo\Evidencia;

/** R-08: guarda el artefacto direccionado por su SHA-256 y confirma que sigue intacto. Fail-closed: si no confirma, lanza. */
interface AlmacenEvidencias
{
    /** @throws AlmacenNoDisponible */
    public function guardar(string $tipo, string $bytes): Evidencia;

    public function confirmada(string $sha256): bool;
}
