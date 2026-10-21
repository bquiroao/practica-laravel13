<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\Comandos;

/** Entrada de RegistrarPaciente. Solo tipos primitivos: Presentación no conoce el Dominio. */
final class ComandoRegistrar
{
    /** @param array<string, mixed> $datos ver FormularioPaciente */
    public function __construct(
        public readonly string $actor,
        public readonly string $hospital,
        public readonly array $datos,
        public readonly bool $confirmaPeseACoincidencia = false,
        public readonly ?string $motivo = null,
    ) {
    }
}
