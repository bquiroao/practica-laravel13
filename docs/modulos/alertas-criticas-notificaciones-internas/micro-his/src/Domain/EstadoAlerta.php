<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

/** Ciclo de vida: CREADA → ENVIADA → ACUSADA → CERRADA; ENVIADA/CREADA → ESCALADA. */
enum EstadoAlerta: string
{
    case CREADA = 'CREADA';
    case ENVIADA = 'ENVIADA';
    case ESCALADA = 'ESCALADA';
    case ACUSADA = 'ACUSADA';
    case CERRADA = 'CERRADA';

    public function esperaAcuse(): bool
    {
        return $this === self::ENVIADA || $this === self::ESCALADA;
    }
}
