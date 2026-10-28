<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Modelo;

/**
 * Semana 6 (CU-07, R-10): la anulación es un HECHO NUEVO sobre una prescripción emitida, no una edición.
 * La prescripción original no cambia; su estado «ANULADA» se deriva de que exista su anulación.
 */
final readonly class Anulacion
{
    public function __construct(
        public int $prescripcionId,
        public string $hospital,
        public string $motivo,
        public string $anuladaPor,
        public \DateTimeImmutable $anuladaEn,
    ) {
    }

    /** @return array{motivo: string, anulada_por: string, anulada_en: string} */
    public function aArreglo(): array
    {
        return ['motivo' => $this->motivo, 'anulada_por' => $this->anuladaPor, 'anulada_en' => $this->anuladaEn->format(\DATE_ATOM)];
    }
}
