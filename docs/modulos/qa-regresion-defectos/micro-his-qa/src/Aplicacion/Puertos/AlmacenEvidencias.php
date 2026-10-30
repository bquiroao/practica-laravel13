<?php

declare(strict_types=1);

namespace Qa\Aplicacion\Puertos;

use Qa\Dominio\Evidencia\EvidenciaVerificable;

/** Almacén de evidencias (otro sistema). guardar() lanza AlmacenNoDisponible si no confirma; nunca inventa una ubicación. */
interface AlmacenEvidencias
{
    /** @throws AlmacenNoDisponible */
    public function guardar(string $tenant, EvidenciaVerificable $evidencia): string;

    /** @throws AlmacenNoDisponible */
    public function leer(string $ubicacion): string;
}
