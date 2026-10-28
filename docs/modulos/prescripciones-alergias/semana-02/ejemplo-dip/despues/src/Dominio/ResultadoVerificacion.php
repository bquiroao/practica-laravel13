<?php

declare(strict_types=1);

namespace Prescripciones\Dominio;

/** Resultado de comparar los medicamentos con las alergias activas. */
final readonly class ResultadoVerificacion
{
    public const SIN_HALLAZGOS = 'SIN_HALLAZGOS';
    public const ADVERTENCIA = 'ADVERTENCIA';
    public const BLOQUEO = 'BLOQUEO';

    /** @param list<Hallazgo> $hallazgos */
    public function __construct(public string $estado, public array $hallazgos)
    {
    }

    /** @param list<Hallazgo> $hallazgos */
    public static function desde(array $hallazgos): self
    {
        $estado = self::SIN_HALLAZGOS;
        foreach ($hallazgos as $h) {
            if ($h->bloquea) {
                return new self(self::BLOQUEO, $hallazgos);
            }
            $estado = self::ADVERTENCIA;
        }

        return new self($estado, $hallazgos);
    }

    /** Código de la respuesta de bloqueo: la coincidencia directa manda sobre la cruzada. */
    public function codigoBloqueo(): string
    {
        foreach ($this->hallazgos as $h) {
            if ($h->bloquea && $h->tipo === Hallazgo::DIRECTA) {
                return 'BLOQUEO_ALERGIA';
            }
        }

        return 'BLOQUEO_ALERGIA_CRUZADA';
    }

    /** @return list<array<string, mixed>> */
    public function hallazgosComoArreglo(): array
    {
        return array_map(static fn (Hallazgo $h): array => $h->aArreglo(), $this->hallazgos);
    }
}
