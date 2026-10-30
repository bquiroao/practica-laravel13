<?php
declare(strict_types=1);

namespace Admision\Application;

use Admision\Domain\Modelo\Admision;

/** Salida mínima: solo código de paciente, nunca nombres (RNF-03). */
final class ResultadoAdmision
{
    public function __construct(
        public readonly string $admisionId,
        public readonly string $codigoPaciente,
        public readonly string $estado,
        public readonly string $camaId,
        public readonly string $camaCodigo,
        public readonly string $servicio,
        public readonly string $origen,
        public readonly string $creadaPor,
        public readonly string $creadaEn,
    ) {
    }

    public static function desde(Admision $a): self
    {
        return new self(
            $a->id, $a->codigoPaciente->valor, $a->estado->value, $a->camaId, $a->camaCodigo,
            $a->requerimiento->servicio, $a->origen->value, $a->creadaPor->usuarioId,
            $a->creadaEn->format(\DateTimeInterface::ATOM),
        );
    }
}
