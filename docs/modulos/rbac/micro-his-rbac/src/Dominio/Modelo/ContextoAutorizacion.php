<?php

declare(strict_types=1);

namespace Rbac\Dominio\Modelo;

/** Lo que cada regla necesita para evaluar: la solicitud y las membresías del usuario en el tenant. */
final class ContextoAutorizacion
{
    /** @param list<Membresia> $membresias */
    public function __construct(
        public readonly SolicitudAutorizacion $solicitud,
        public readonly array $membresias,
    ) {
    }

    /** @return list<Membresia> */
    public function membresiasVigentes(): array
    {
        return array_values(array_filter(
            $this->membresias,
            fn (Membresia $m): bool => $m->estaVigenteEn($this->solicitud->ahora),
        ));
    }
}
