<?php

declare(strict_types=1);

namespace Pacientes\Dominio;

final class SolicitudRegistro
{
    public function __construct(
        public readonly string $actor,
        public readonly string $hospital,
        public readonly DatosPaciente $datos,
        public readonly bool $confirmaPeseACoincidencia = false,
        public readonly ?string $motivo = null,
    ) {
    }
}
