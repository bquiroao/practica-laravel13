<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Resultados;

/** Salida de los casos de uso hacia la Presentación: datos planos, sin objetos de dominio. */
final class NotaResultado
{
    /** @param list<array{id: string, code: string, description: string, type: string}> $diagnosticos */
    public function __construct(
        public readonly string $notaId,
        public readonly string $autorId,
        public readonly string $estado,
        public readonly int $version,
        public readonly ?string $firmadaEn,
        public readonly array $diagnosticos,
    ) {
    }

    /** @return array<string, mixed> */
    public function comoArreglo(): array
    {
        return [
            'clinical_note_id' => $this->notaId,
            'author_user_ref_id' => $this->autorId,
            'status' => $this->estado,
            'version_no' => $this->version,
            'signed_at' => $this->firmadaEn,
            'diagnoses' => $this->diagnosticos,
        ];
    }
}
