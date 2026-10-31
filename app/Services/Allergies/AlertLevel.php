<?php

namespace App\Services\Allergies;

/** Módulo alergias clínicas: nivel de la alerta visual del expediente (RN-08). */
enum AlertLevel: string
{
    case NoRecord = 'SIN_REGISTRO';
    case Informative = 'INFORMATIVA';
    case Moderate = 'MODERADA';
    case High = 'ALTA';
    case Critical = 'CRITICA';

    public function weight(): int
    {
        return match ($this) {
            self::NoRecord => 0,
            self::Informative => 1,
            self::Moderate => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    /** RN-09: ALTA y CRÍTICA exigen reconocimiento. */
    public function requiresAcknowledgement(): bool
    {
        return $this->weight() >= self::High->weight();
    }

    public static function max(self $a, self $b): self
    {
        return $a->weight() >= $b->weight() ? $a : $b;
    }

    public function label(): string
    {
        return match ($this) {
            self::NoRecord => 'Sin alergias registradas',
            self::Informative => 'Informativa',
            self::Moderate => 'Moderada',
            self::High => 'Alta',
            self::Critical => 'Crítica',
        };
    }
}
