<?php
declare(strict_types=1);

namespace Catalogo\Domain\Modelo;

/**
 * RN-04: principio activo + concentración + forma + vía. El principio activo se compara sin
 * mayúsculas ni espacios repetidos, así "Amoxicilina " y "amoxicilina" son el mismo.
 */
final class ClaveEquivalencia
{
    private function __construct(public readonly string $valor)
    {
    }

    public static function de(string $principioActivo, Concentracion $c, FormaFarmaceutica $forma, ViaAdministracion $via): self
    {
        return new self(implode('|', [
            self::normalizarPrincipio($principioActivo),
            $c->valor,
            $c->unidad->value,
            $forma->value,
            $via->value,
        ]));
    }

    public static function normalizarPrincipio(string $texto): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($texto)) ?? '', 'UTF-8');
    }

    public function igualA(self $otra): bool
    {
        return $this->valor === $otra->valor;
    }
}
