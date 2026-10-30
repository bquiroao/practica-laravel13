<?php
declare(strict_types=1);

namespace Alergias\Dominio;

/** Valores reales de allergies.allergy_type (HOSPITAL). */
enum TipoAlergia: string
{
    case Alergia = 'allergy';
    case Intolerancia = 'intolerance';
    case Desconocido = 'unknown';

    public static function desde(string $valor): self
    {
        return self::tryFrom($valor)
            ?? throw new ReglaDominioViolada('TIPO_INVALIDO', "Tipo no permitido: {$valor}");
    }
}
