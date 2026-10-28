<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Modelo;

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

    /** @param array<string, mixed> $a */
    public static function desdeArreglo(array $a): self
    {
        return new self((string) $a['tipo'], (string) $a['medicamento'], (string) $a['alergeno'], (string) $a['severidad'],
            (bool) $a['bloquea'], isset($a['reaccion']) ? (string) $a['reaccion'] : null);
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
