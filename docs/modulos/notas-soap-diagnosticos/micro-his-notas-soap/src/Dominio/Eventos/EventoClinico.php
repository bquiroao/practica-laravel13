<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Eventos;

use DateTimeImmutable;
use NotasSoap\Dominio\Modelo\NotaSoap;

/**
 * Hecho clínico que otros sistemas pueden necesitar saber (por ejemplo, el repositorio compartido CENTRAL).
 * Lleva solo identificadores: el contenido de la nota nunca sale del hospital.
 */
final class EventoClinico
{
    public const NOTA_SOAP_FIRMADA = 'NotaSoapFirmada';

    /** @param array<string, string|int|null> $datos */
    public function __construct(
        public readonly string $id,
        public readonly string $tipo,
        public readonly string $agregadoId,
        public readonly DateTimeImmutable $ocurridoEn,
        public readonly array $datos,
    ) {
    }

    public static function notaFirmada(string $id, NotaSoap $nota): self
    {
        $momento = $nota->firmadaEn ?? throw new \LogicException('La nota no está firmada.');

        return new self($id, self::NOTA_SOAP_FIRMADA, $nota->id, $momento, [
            'clinical_note_id' => $nota->id,
            'medical_record_id' => $nota->expedienteId,
            'care_episode_id' => $nota->episodioId,
            'author_user_ref_id' => $nota->autorId,
            'version_no' => $nota->version,
            'signed_at' => $momento->format(DATE_ATOM),
        ]);
    }
}
