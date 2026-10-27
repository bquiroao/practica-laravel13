<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Modelo;

/**
 * Prescripción EMITIDA con su verificación (R-12). Inmutable: no tiene ningún método que la cambie (R-10);
 * la base lo refuerza con un trigger que rechaza UPDATE y DELETE.
 */
final readonly class Prescripcion
{
    public const EMITIDA = 'EMITIDA';

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string>               $alergenosEvaluados alérgenos activos contra los que se verificó
     */
    public function __construct(
        public ?int $id,
        public string $hospital,
        public string $paciente,
        public string $prescriptor,
        public string $clave,
        public string $huella,
        public array $items,
        public ResultadoVerificacion $verificacion,
        public array $alergenosEvaluados,
        public ?string $justificacion,
        public bool $confirmaSinAlergias,
        public \DateTimeImmutable $emitidaEn,
        public string $estado = self::EMITIDA,
    ) {
    }

    public function conId(int $id): self
    {
        return new self($id, $this->hospital, $this->paciente, $this->prescriptor, $this->clave, $this->huella, $this->items,
            $this->verificacion, $this->alergenosEvaluados, $this->justificacion, $this->confirmaSinAlergias, $this->emitidaEn, $this->estado);
    }

    /** @return array<string, mixed> vista para farmacia y enfermería (CU-09) */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'paciente' => $this->paciente,
            'prescriptor' => $this->prescriptor,
            'estado' => $this->estado,
            'emitida_en' => $this->emitidaEn->format(\DATE_ATOM),
            'items' => $this->items,
            'verificacion' => [
                'resultado' => $this->verificacion->estado,
                'hallazgos' => $this->verificacion->hallazgosComoArreglo(),
                'alergenos_evaluados' => $this->alergenosEvaluados,
                'justificacion' => $this->justificacion,
                'confirma_sin_alergias' => $this->confirmaSinAlergias,
            ],
        ];
    }
}
