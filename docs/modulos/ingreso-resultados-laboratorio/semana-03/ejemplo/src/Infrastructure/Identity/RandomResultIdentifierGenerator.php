<?php

declare(strict_types=1);

namespace LabResults\Infrastructure\Identity;

use LabResults\Application\ResultIdentifierGenerator;

final class RandomResultIdentifierGenerator implements ResultIdentifierGenerator
{
    public function generate(): string
    {
        return 'RES-' . strtoupper(bin2hex(random_bytes(16)));
    }
}
