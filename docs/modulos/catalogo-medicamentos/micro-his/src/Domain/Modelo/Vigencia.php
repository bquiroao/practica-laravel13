<?php
declare(strict_types=1);

namespace Catalogo\Domain\Modelo;

/** Intervalo [desde, hasta): el día "hasta" ya no está vigente. hasta = null significa abierta. */
final class Vigencia
{
    public function __construct(public readonly \DateTimeImmutable $desde, public readonly ?\DateTimeImmutable $hasta = null)
    {
        if ($hasta !== null && $hasta <= $desde) {
            throw new \InvalidArgumentException('La vigencia debe terminar después de empezar');
        }
    }

    public function abierta(): bool
    {
        return $this->hasta === null;
    }

    /** RN-03: vigente en la fecha si desde <= fecha < hasta. */
    public function vigenteEn(\DateTimeImmutable $fecha): bool
    {
        return $this->desde <= $fecha && ($this->hasta === null || $fecha < $this->hasta);
    }

    /** RN-01 y RN-04: dos intervalos se cruzan si cada uno empieza antes de que el otro termine. */
    public function seCruzaCon(self $otra): bool
    {
        $esteAntesDeQueTermineOtra = $otra->hasta === null || $this->desde < $otra->hasta;
        $otraAntesDeQueTermineEsta = $this->hasta === null || $otra->desde < $this->hasta;
        return $esteAntesDeQueTermineOtra && $otraAntesDeQueTermineEsta;
    }

    public function cerrarEn(\DateTimeImmutable $hasta): self
    {
        return new self($this->desde, $hasta);
    }
}
