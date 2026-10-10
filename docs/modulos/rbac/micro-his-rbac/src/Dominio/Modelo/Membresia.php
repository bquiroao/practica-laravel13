<?php

declare(strict_types=1);

namespace Rbac\Dominio\Modelo;

/** Rol de un usuario dentro de un hospital, con estado y vigencia. */
final class Membresia
{
    public function __construct(
        public readonly string $usuarioId,
        public readonly string $hospitalId,
        public readonly string $rol,
        public readonly string $estado,
        public readonly \DateTimeImmutable $validoDesde,
        public readonly ?\DateTimeImmutable $validoHasta = null,
    ) {
    }

    public function estaVigenteEn(\DateTimeImmutable $momento): bool
    {
        return $this->estado === 'active'
            && $this->validoDesde <= $momento
            && ($this->validoHasta === null || $momento < $this->validoHasta);
    }
}
