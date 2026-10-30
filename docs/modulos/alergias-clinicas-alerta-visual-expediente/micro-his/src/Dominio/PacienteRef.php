<?php
declare(strict_types=1);

namespace Alergias\Dominio;

/** Lo único que este módulo conoce del paciente (dueño: módulo pacientes). */
final class PacienteRef
{
    public function __construct(
        public readonly string $id,
        public readonly string $codigo,
        public readonly string $estado,
    ) {
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'active';
    }
}
