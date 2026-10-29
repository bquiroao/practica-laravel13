<?php
declare(strict_types=1);

namespace Admision\Application\Puertos;

interface GeneradorId
{
    public function nuevo(): string;
}
