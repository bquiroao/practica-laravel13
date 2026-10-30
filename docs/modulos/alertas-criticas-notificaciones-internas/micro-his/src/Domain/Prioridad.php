<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

enum Prioridad: string
{
    case CRITICA = 'CRITICA';
    case ALTA = 'ALTA';

    public static function desde(string $valor): self
    {
        return self::tryFrom(strtoupper(trim($valor)))
            ?? throw new ReglaDominioException('RN-01', "Prioridad no válida: «{$valor}» (use CRITICA o ALTA)");
    }
}
