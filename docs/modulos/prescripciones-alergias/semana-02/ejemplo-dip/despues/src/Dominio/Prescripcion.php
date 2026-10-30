<?php

declare(strict_types=1);

namespace Prescripciones\Dominio;

/** Prescripción EMITIDA con su verificación. Inmutable: no tiene ningún método que la cambie (R-10). */
final readonly class Prescripcion
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(
        public ?int $id,
        public string $hospital,
        public string $paciente,
        public string $prescriptor,
        public string $clave,
        public string $huella,
        public array $items,
        public ResultadoVerificacion $verificacion,
        public ?string $justificacion,
        public bool $confirmaSinAlergias,
        public \DateTimeImmutable $emitidaEn,
        public string $estado = 'EMITIDA',
    ) {
    }

    public function conId(int $id): self
    {
        return new self($id, $this->hospital, $this->paciente, $this->prescriptor, $this->clave, $this->huella, $this->items,
            $this->verificacion, $this->justificacion, $this->confirmaSinAlergias, $this->emitidaEn, $this->estado);
    }
}
