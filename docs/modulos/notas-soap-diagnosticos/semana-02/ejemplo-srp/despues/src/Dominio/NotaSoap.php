<?php

declare(strict_types=1);

namespace NotasSoap\Dominio;

use DateTimeImmutable;
use NotasSoap\Dominio\Excepciones\NotaFirmada;

/** Entidad nota SOAP. Sabe si está completa (por SeccionesSoap) y controla su firma (R-07). */
final class NotaSoap
{
    private function __construct(
        public readonly ?string $id,
        public readonly string $expedienteId,
        public readonly string $episodioId,
        public readonly string $autorId,
        public readonly SeccionesSoap $secciones,
        public readonly int $version,
        public readonly string $estado,
        public readonly ?DateTimeImmutable $firmadaEn,
    ) {
    }

    public static function registrar(string $expedienteId, string $episodioId, string $autorId, SeccionesSoap $secciones): self
    {
        return new self(null, $expedienteId, $episodioId, $autorId, $secciones, 1, 'borrador', null);
    }

    public function conId(string $id): self
    {
        return new self($id, $this->expedienteId, $this->episodioId, $this->autorId, $this->secciones, $this->version, $this->estado, $this->firmadaEn);
    }

    public function firmar(DateTimeImmutable $momento): self
    {
        if ($this->estado === 'firmada') {
            throw new NotaFirmada('La nota ya está firmada y no se puede modificar.');
        }

        return new self($this->id, $this->expedienteId, $this->episodioId, $this->autorId, $this->secciones, $this->version, 'firmada', $momento);
    }
}
