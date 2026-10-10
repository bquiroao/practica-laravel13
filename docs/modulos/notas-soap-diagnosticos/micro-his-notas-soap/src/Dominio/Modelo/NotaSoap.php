<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Modelo;

use DateTimeImmutable;
use NotasSoap\Dominio\Excepciones\NotaFirmada;

/** Entidad nota SOAP (clinical_notes con note_type = 'soap'). Controla su firma (R-07). */
final class NotaSoap
{
    public const BORRADOR = 'draft';
    public const FIRMADA = 'signed';

    public function __construct(
        public readonly string $id,
        public readonly string $expedienteId,
        public readonly string $episodioId,
        public readonly string $autorId,
        public readonly SeccionesSoap $secciones,
        public readonly int $version,
        public readonly string $estado,
        public readonly DateTimeImmutable $creadaEn,
        public readonly ?DateTimeImmutable $firmadaEn,
    ) {
    }

    public static function registrar(string $id, string $expedienteId, string $episodioId, string $autorId, SeccionesSoap $secciones, DateTimeImmutable $ahora): self
    {
        return new self($id, $expedienteId, $episodioId, $autorId, $secciones, 1, self::BORRADOR, $ahora, null);
    }

    public function firmar(DateTimeImmutable $momento): self
    {
        if ($this->estado === self::FIRMADA) {
            throw new NotaFirmada('La nota ya está firmada y no se puede modificar.');
        }

        return new self($this->id, $this->expedienteId, $this->episodioId, $this->autorId, $this->secciones, $this->version, self::FIRMADA, $this->creadaEn, $momento);
    }
}
