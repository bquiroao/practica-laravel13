<?php

declare(strict_types=1);

namespace Prescripciones\Dominio;

/** Un conflicto entre un medicamento y una alergia activa. */
final readonly class Hallazgo
{
    public const DIRECTA = 'DIRECTA';
    public const CRUZADA = 'CRUZADA';

    public function __construct(
        public string $tipo,
        public string $medicamento,
        public string $alergeno,
        public string $severidad,
        public bool $bloquea,
        public ?string $reaccion,
    ) {
    }

    /** @return array<string, mixed> */
    public function aArreglo(): array
    {
        return [
            'tipo' => $this->tipo,
            'medicamento' => $this->medicamento,
            'alergeno' => $this->alergeno,
            'severidad' => $this->severidad,
            'bloquea' => $this->bloquea,
            'reaccion' => $this->reaccion,
        ];
    }
}
