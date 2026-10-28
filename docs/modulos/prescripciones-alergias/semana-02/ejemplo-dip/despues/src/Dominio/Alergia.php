<?php

declare(strict_types=1);

namespace Prescripciones\Dominio;

/**
 * Una alergia del expediente, con los mismos valores que la tabla real `allergies` del HIS:
 * alérgeno por código o por nombre, tipo, severidad (incluye «unknown») y estado.
 */
final readonly class Alergia
{
    public const SEVERIDADES = ['mild', 'moderate', 'severe', 'life_threatening', 'unknown'];

    public function __construct(
        public ?string $codigo,
        public ?string $nombre,
        public string $tipo,
        public string $severidad,
        public string $estado,
        public ?string $reaccion = null,
    ) {
        if (($codigo === null || trim($codigo) === '') && ($nombre === null || trim($nombre) === '')) {
            throw new \InvalidArgumentException('Una alergia necesita código o nombre del alérgeno.');
        }
        if (!in_array($severidad, self::SEVERIDADES, true)) {
            throw new \InvalidArgumentException("Severidad desconocida: $severidad");
        }
    }

    public function activa(): bool
    {
        return $this->estado === 'active';
    }

    /** Severidad que impide justificar una reactividad cruzada (R-05): lo grave y lo que nadie midió. */
    public function impideJustificar(): bool
    {
        return in_array($this->severidad, ['life_threatening', 'unknown'], true);
    }

    public function alergeno(): string
    {
        return $this->codigo ?? (string) $this->nombre;
    }
}
