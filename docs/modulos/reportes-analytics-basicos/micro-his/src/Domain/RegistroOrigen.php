<?php
declare(strict_types=1);

namespace MicroHis\Domain;

/** Nivel 3. Un registro real que forma el indicador, con datos mínimos (RN-09). */
final class RegistroOrigen
{
    public function __construct(
        public readonly string $cama,
        public readonly string $paciente,
        public readonly string $ingreso,
        public readonly ?string $egreso,
    ) {
        if (preg_match('/^PAC-\d{5}$/', $paciente) !== 1) {
            throw new \DomainException('El paciente solo se identifica por su código (RN-09)');
        }
    }
}
