<?php
declare(strict_types=1);

namespace Admision\Application;

/** Datos de entrada tal como llegan de la presentación; se validan en el caso de uso. */
final class SolicitudAdmision
{
    public function __construct(
        public readonly string $codigoPaciente,
        public readonly string $servicio,
        public readonly string $tipoCama,
        public readonly bool $requiereAislamiento,
        public readonly string $origen,
        public readonly string $camaId,
    ) {
    }
}
