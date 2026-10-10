<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Resultados;

use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Modelo\NotaSoap;

/**
 * Salida de los casos de uso hacia la Presentación: datos planos, sin objetos de dominio.
 * Las vistas (JSON o HTML) solo reciben esto; nunca ven NotaSoap ni Diagnostico.
 */
final class NotaResultado
{
    /**
     * @param array{subjetivo: string, objetivo: string, analisis: string, plan: string} $soap
     * @param list<array{id: string, code: string, description: string, type: string}> $diagnosticos
     */
    public function __construct(
        public readonly string $notaId,
        public readonly string $expedienteId,
        public readonly string $episodioId,
        public readonly string $autorId,
        public readonly string $estado,
        public readonly int $version,
        public readonly ?string $firmadaEn,
        public readonly array $soap,
        public readonly array $diagnosticos,
    ) {
    }

    /** @param list<Diagnostico> $diagnosticos */
    public static function desde(NotaSoap $nota, array $diagnosticos): self
    {
        return new self(
            $nota->id,
            $nota->expedienteId,
            $nota->episodioId,
            $nota->autorId,
            $nota->estado,
            $nota->version,
            $nota->firmadaEn?->format(DATE_ATOM),
            [
                'subjetivo' => $nota->secciones->subjetivo,
                'objetivo' => $nota->secciones->objetivo,
                'analisis' => $nota->secciones->analisis,
                'plan' => $nota->secciones->plan,
            ],
            array_map(static fn (Diagnostico $d): array => [
                'id' => $d->id, 'code' => $d->codigo, 'description' => $d->descripcion, 'type' => $d->tipo->value,
            ], $diagnosticos),
        );
    }

    /** @return array<string, mixed> */
    public function comoArreglo(): array
    {
        return [
            'clinical_note_id' => $this->notaId,
            'medical_record_id' => $this->expedienteId,
            'care_episode_id' => $this->episodioId,
            'author_user_ref_id' => $this->autorId,
            'status' => $this->estado,
            'version_no' => $this->version,
            'signed_at' => $this->firmadaEn,
            'soap' => $this->soap,
            'diagnoses' => $this->diagnosticos,
        ];
    }
}
