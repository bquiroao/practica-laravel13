<?php
declare(strict_types=1);

namespace Alergias\Dominio;

/** Valores reales de allergies.severity (HOSPITAL). */
enum Severidad: string
{
    case Leve = 'mild';
    case Moderada = 'moderate';
    case Grave = 'severe';
    case PoneEnRiesgoLaVida = 'life_threatening';
    case Desconocida = 'unknown';

    public static function desde(string $valor): self
    {
        return self::tryFrom($valor)
            ?? throw new ReglaDominioViolada('SEVERIDAD_INVALIDA', "Severidad no permitida: {$valor}");
    }
}
