<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Reglas;

use Auditoria\Dominio\Puertos\Reloj;
use Auditoria\Dominio\Modelo\Rechazo;
use Auditoria\Dominio\Modelo\SolicitudRegistro;

/** R-01: ocurrido_en en ISO 8601 con zona y no futuro, con una tolerancia para relojes desfasados. */
final class ReglaFechaNoFutura implements ReglaMovimiento
{
    public function __construct(private readonly Reloj $reloj, private readonly int $toleranciaMinutos = 5)
    {
    }

    public function evaluar(SolicitudRegistro $s): ?Rechazo
    {
        $ocurrido = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $s->datos['ocurrido_en']);
        if ($ocurrido === false) {
            return Rechazo::invalido(['ocurrido_en' => 'Fecha ISO 8601 con zona.']);
        }
        if ($ocurrido > $this->reloj->ahora()->modify("+{$this->toleranciaMinutos} minutes")) {
            return Rechazo::invalido(['ocurrido_en' => 'No puede ser futura.']);
        }

        return null;
    }
}
