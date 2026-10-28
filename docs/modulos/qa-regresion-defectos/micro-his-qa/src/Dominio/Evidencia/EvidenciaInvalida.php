<?php

declare(strict_types=1);

namespace Qa\Dominio\Evidencia;

final class EvidenciaInvalida extends \InvalidArgumentException
{
    public function __construct(public readonly string $codigo)
    {
        parent::__construct($codigo);
    }
}
