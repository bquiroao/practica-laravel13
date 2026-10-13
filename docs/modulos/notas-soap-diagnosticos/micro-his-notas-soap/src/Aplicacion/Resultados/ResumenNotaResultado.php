<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Resultados;

use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Modelo\NotaSoap;

/**
 * Resumen de una nota para listados: SIN el texto de las secciones (mínimo necesario).
 * Quien necesite el contenido pide la nota completa por su id y queda registrado.
 */
final class ResumenNotaResultado
{
    /** @param list<string> $codigosCie10 */
    public function __construct(
        public readonly string $notaId,
        public readonly string $episodioId,
        public readonly string $autorId,
        public readonly string $estado,
        public readonly int $version,
        public readonly string $creadaEn,
        public readonly ?string $firmadaEn,
        public readonly array $codigosCie10,
    ) {
    }

    /** @param list<Diagnostico> $diagnosticos */
    public static function desde(NotaSoap $nota, array $diagnosticos): self
    {
        return new self(
            $nota->id,
            $nota->episodioId,
            $nota->autorId,
            $nota->estado,
            $nota->version,
            $nota->creadaEn->format(DATE_ATOM),
            $nota->firmadaEn?->format(DATE_ATOM),
            array_map(static fn (Diagnostico $d): string => $d->codigo, $diagnosticos),
        );
    }

    /** @return array<string, mixed> */
    public function comoArreglo(): array
    {
        return [
            'clinical_note_id' => $this->notaId,
            'care_episode_id' => $this->episodioId,
            'author_user_ref_id' => $this->autorId,
            'status' => $this->estado,
            'version_no' => $this->version,
            'created_at' => $this->creadaEn,
            'signed_at' => $this->firmadaEn,
            'diagnosis_codes' => $this->codigosCie10,
        ];
    }
}
