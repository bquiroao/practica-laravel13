<?php

declare(strict_types=1);

namespace Prescripciones\Dominio;

/** Lo que el dominio necesita saber de un medicamento del catálogo (dato inmutable). */
final readonly class Medicamento
{
    /** @param list<string> $vias */
    public function __construct(
        public string $codigo,
        public string $nombre,
        public string $principioActivoCodigo,
        public string $principioActivo,
        public string $grupo,
        public string $unidad,
        public float $dosisMaximaDiaria,
        public array $vias,
        public bool $activo,
    ) {
    }
}
