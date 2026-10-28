<?php

declare(strict_types=1);

namespace MicroHis\Infrastructure\Identity;

use MicroHis\Application\SpecimenIdentifierGenerator;

final class RandomSpecimenIdentifierGenerator implements SpecimenIdentifierGenerator
{
    public function generate(): string
    {
        return 'SP-' . strtoupper(bin2hex(random_bytes(16)));
    }
}
