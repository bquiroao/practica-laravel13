<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Modelo;

/** Qué dice el Dominio sobre emitir una prescripción ya verificada (semana 7). */
final readonly class DecisionEmision
{
    public const PERMITIDA = 'PERMITIDA';
    public const BLOQUEADA = 'BLOQUEADA';
    public const REQUIERE_JUSTIFICACION = 'REQUIERE_JUSTIFICACION';
    public const REQUIERE_CONFIRMACION = 'REQUIERE_CONFIRMACION';

    private function __construct(
        public string $tipo,
        public ?string $codigoBloqueo = null,
        public ?string $justificacion = null,
        public bool $confirmacionRegistrada = false,
    ) {
    }

    /** @param string|null $justificacion ya normalizada; solo cuando hubo una advertencia */
    public static function permitida(?string $justificacion, bool $confirmacionRegistrada): self
    {
        return new self(self::PERMITIDA, null, $justificacion, $confirmacionRegistrada);
    }

    public static function bloqueada(string $codigo): self
    {
        return new self(self::BLOQUEADA, $codigo);
    }

    public static function requiereJustificacion(): self
    {
        return new self(self::REQUIERE_JUSTIFICACION);
    }

    public static function requiereConfirmacion(): self
    {
        return new self(self::REQUIERE_CONFIRMACION);
    }

    public function permiteEmitir(): bool
    {
        return $this->tipo === self::PERMITIDA;
    }
}
