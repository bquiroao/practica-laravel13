<?php

declare(strict_types=1);

namespace Prescripciones\Adaptadores\EnMemoria;

use Prescripciones\Aplicacion\Puertos\Reloj;

final class RelojFijo implements Reloj
{
    private \DateTimeImmutable $momento;

    public function __construct(?\DateTimeImmutable $momento = null)
    {
        $this->momento = $momento ?? new \DateTimeImmutable('2026-10-27T15:00:00Z');
    }

    public function ahora(): \DateTimeImmutable
    {
        return $this->momento;
    }
}
