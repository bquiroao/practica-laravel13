<?php
declare(strict_types=1);

namespace Catalogo\Domain\Puertos;

/** Identificadores UUID de cada versión. */
interface GeneradorId
{
    public function nuevo(): string;
}
