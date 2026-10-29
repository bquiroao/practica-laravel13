<?php
declare(strict_types=1);

namespace Alergias\Dominio;

/** Nivel de la alerta visual del expediente (RN-08). */
enum NivelAlerta: string
{
    case SinRegistro = 'SIN_REGISTRO';
    case Informativa = 'INFORMATIVA';
    case Moderada = 'MODERADA';
    case Alta = 'ALTA';
    case Critica = 'CRITICA';

    public function peso(): int
    {
        return match ($this) {
            self::SinRegistro => 0,
            self::Informativa => 1,
            self::Moderada => 2,
            self::Alta => 3,
            self::Critica => 4,
        };
    }

    /** RN-09: ALTA y CRITICA exigen reconocimiento. */
    public function exigeReconocimiento(): bool
    {
        return $this->peso() >= self::Alta->peso();
    }

    public static function mayor(self $a, self $b): self
    {
        return $a->peso() >= $b->peso() ? $a : $b;
    }
}
