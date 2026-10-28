<?php

declare(strict_types=1);

namespace MicroHis\Application;

use MicroHis\Domain\SpecimenReception;

interface SpecimenRepository
{
    public function save(SpecimenReception $reception): void;
}

interface SpecimenIdentifierGenerator
{
    public function generate(): string;
}
