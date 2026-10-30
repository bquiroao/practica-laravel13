<?php
declare(strict_types=1);

namespace Catalogo\Domain\Puertos;

/** "Hoy" se inyecta para que las reglas de vigencia se puedan probar con fechas fijas. */
interface Reloj
{
    public function hoy(): \DateTimeImmutable;
}
