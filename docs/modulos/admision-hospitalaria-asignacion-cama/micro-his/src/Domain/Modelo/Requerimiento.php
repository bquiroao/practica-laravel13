<?php
declare(strict_types=1);

namespace Admision\Domain\Modelo;

use Admision\Domain\Excepciones\DatoInvalido;

/** Lo que necesita el paciente: servicio, tipo de cama y aislamiento (RN-03). */
final class Requerimiento
{
    public function __construct(
        public readonly string $servicio,
        public readonly TipoCama $tipo,
        public readonly bool $requiereAislamiento,
    ) {
        if (preg_match('/^[A-Z_]{3,40}\z/', $servicio) !== 1) {
            throw new DatoInvalido('Servicio inválido.');
        }
    }

    public static function desde(string $servicio, string $tipo, bool $requiereAislamiento): self
    {
        $tipoCama = TipoCama::tryFrom($tipo) ?? throw new DatoInvalido('Tipo de cama inválido.');
        return new self($servicio, $tipoCama, $requiereAislamiento);
    }
}
