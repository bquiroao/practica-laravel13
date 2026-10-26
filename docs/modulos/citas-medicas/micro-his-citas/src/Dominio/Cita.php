<?php
declare(strict_types=1);

namespace MicroHis\Citas\Dominio;

/** Violación de una regla de negocio. El código (E2..E7, NF) lo traduce a HTTP la capa de presentación. */
final class ReglaDeDominio extends \DomainException
{
    public function __construct(public readonly string $codigo, string $mensaje) { parent::__construct($mensaje); }
}

/** Estados de `appointments.status` y sus transiciones (R-07). Una cita completada o cancelada ya no cambia. */
enum EstadoCita: string
{
    case Programada = 'scheduled'; case Confirmada = 'confirmed'; case Completada = 'completed'; case Cancelada = 'cancelled';

    public function validarCambioA(self $nuevo): void
    {
        $permitidas = match ($this) { self::Programada => [self::Confirmada, self::Cancelada], self::Confirmada => [self::Cancelada], default => [] };
        if (!in_array($nuevo, $permitidas, true)) throw new ReglaDeDominio('E7', "Una cita {$this->value} no puede pasar a {$nuevo->value}.");
    }
}

/** Intervalo [inicio, fin) de una cita (R-01): en el futuro y con fin posterior al inicio. */
final class Horario
{
    private function __construct(public readonly int $inicio, public readonly int $fin) {}

    public static function validar(int $inicio, int $fin, int $ahora): self
    {
        if ($inicio <= $ahora || $fin <= $inicio) throw new ReglaDeDominio('E2', 'El horario debe ser futuro y terminar después de empezar.');
        return new self($inicio, $fin);
    }
}
