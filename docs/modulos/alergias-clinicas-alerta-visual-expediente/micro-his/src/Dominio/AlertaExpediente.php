<?php
declare(strict_types=1);

namespace Alergias\Dominio;

/** Resultado de CU-04: lo que se muestra en el expediente. */
final class AlertaExpediente
{
    /** @param list<array{alergia: Alergia, nivel: NivelAlerta}> $detalle ordenado de mayor a menor */
    public function __construct(
        public readonly string $codigoPaciente,
        public readonly NivelAlerta $nivel,
        public readonly array $detalle,
        public readonly string $huella,
        public readonly bool $reconocida = false,
    ) {
    }

    /** RN-09: pendiente si el nivel lo exige y no hay reconocimiento de esta huella. */
    public function requiereReconocimiento(): bool
    {
        return $this->nivel->exigeReconocimiento() && !$this->reconocida;
    }

    public function conReconocimiento(bool $reconocida): self
    {
        return new self($this->codigoPaciente, $this->nivel, $this->detalle, $this->huella, $reconocida);
    }
}
