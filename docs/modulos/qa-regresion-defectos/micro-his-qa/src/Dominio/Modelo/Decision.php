<?php

declare(strict_types=1);

namespace Qa\Dominio\Modelo;

/** Decisión de cierre (R-09): GO o NO_GO, la tasa de aprobados y las causas de un NO_GO. */
final readonly class Decision
{
    public const GO = 'GO';
    public const NO_GO = 'NO_GO';

    /** @param list<string> $causas */
    public function __construct(public string $valor, public float $tasa, public array $causas)
    {
    }
}
