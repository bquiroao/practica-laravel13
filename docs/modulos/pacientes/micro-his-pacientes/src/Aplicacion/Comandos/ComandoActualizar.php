<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\Comandos;

/** Entrada de ActualizarPaciente: la versión leída viaja en If-Match. */
final class ComandoActualizar
{
    /** @param array<string, mixed> $datos ver FormularioPaciente */
    public function __construct(
        public readonly string $actor,
        public readonly string $hospital,
        public readonly string $codigo,
        public readonly int $versionLeida,
        public readonly array $datos,
        public readonly ?string $motivo = null,
    ) {
    }
}
