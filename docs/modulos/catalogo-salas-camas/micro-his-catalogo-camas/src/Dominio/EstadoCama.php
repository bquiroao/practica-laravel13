<?php
declare(strict_types=1);

namespace MicroHis\Catalogo\Dominio;

/** Violación de una regla de negocio. El código (E2..E7, NF) lo traduce a HTTP la capa de presentación. */
final class ReglaDeDominio extends \DomainException
{
    public function __construct(public readonly string $codigo, string $mensaje) { parent::__construct($mensaje); }
}

/** Estados de `beds.status` y sus transiciones manuales (R-05, R-06). Sin dependencias externas. */
enum EstadoCama: string
{
    case Disponible = 'available';
    case Ocupada = 'occupied';
    case Mantenimiento = 'maintenance';
    case Inactiva = 'inactive';

    public function validarCambioA(self $nuevo): void
    {
        if ($this === self::Ocupada) throw new ReglaDeDominio('E5', 'La cama está ocupada: se gestiona desde admisión.');
        $permitidas = match ($this) {
            self::Disponible => [self::Mantenimiento, self::Inactiva],
            self::Mantenimiento => [self::Disponible, self::Inactiva],
            self::Inactiva => [self::Disponible],
        };
        if (!in_array($nuevo, $permitidas, true)) throw new ReglaDeDominio('E6', "Transición no permitida: {$this->value} -> {$nuevo->value}.");
    }
}
