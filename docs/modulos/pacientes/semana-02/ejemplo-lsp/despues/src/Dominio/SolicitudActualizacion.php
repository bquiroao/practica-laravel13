<?php

declare(strict_types=1);

namespace Pacientes\Dominio;

final class SolicitudActualizacion
{
    public function __construct(
        public readonly string $actor,
        public readonly string $hospital,
        public readonly string $codigo,
        public readonly int $versionLeida,
        public readonly DatosPaciente $datos,
        public readonly ?string $motivo = null,
    ) {
    }
}
