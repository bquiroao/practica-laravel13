<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion;

final readonly class Respuesta
{
    /** @param array<string, mixed> $datos */
    public function __construct(public int $estado, public string $codigo, public array $datos = [])
    {
    }
}
