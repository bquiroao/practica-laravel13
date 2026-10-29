<?php
declare(strict_types=1);

namespace Alergias\Dominio;

/** RN-01: código o nombre; al menos uno con texto. */
final class Alergeno
{
    public readonly ?string $codigo;
    public readonly ?string $nombre;

    public function __construct(?string $codigo, ?string $nombre)
    {
        $this->codigo = self::limpiar($codigo);
        $this->nombre = self::limpiar($nombre);
        if ($this->codigo === null && $this->nombre === null) {
            throw new ReglaDominioViolada('ALERGENO_REQUERIDO', 'Indique el código o el nombre del alérgeno.');
        }
    }

    /** Misma normalización que el índice allergies_active_equivalent_unique. */
    public function clave(): string
    {
        return mb_strtolower($this->codigo ?? $this->nombre);
    }

    public function etiqueta(): string
    {
        return $this->nombre ?? $this->codigo;
    }

    private static function limpiar(?string $texto): ?string
    {
        $texto = $texto === null ? null : trim($texto);
        return ($texto === null || $texto === '') ? null : $texto;
    }
}
