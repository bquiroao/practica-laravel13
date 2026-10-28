<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Modelo;

use DateTimeImmutable;
use NotasSoap\Dominio\Excepciones\MotivoCorreccionInsuficiente;
use NotasSoap\Dominio\Excepciones\NotaFirmada;
use NotasSoap\Dominio\Excepciones\NotaNoFirmada;

/**
 * Entidad nota SOAP (clinical_notes con note_type = 'soap'). Controla su firma (R-07).
 * Semana 6: una nota firmada no se edita; se CORRIGE creando la versión siguiente, que apunta
 * a la anterior (supersedes_id) y guarda el motivo (correction_reason).
 */
final class NotaSoap
{
    public const BORRADOR = 'draft';
    public const FIRMADA = 'signed';
    public const MOTIVO_MINIMO = 10;

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
        public readonly ?string $reemplazaA = null,
        public readonly ?string $motivoCorreccion = null,
    ) {
    }

    public static function registrar(string $id, string $expedienteId, string $episodioId, string $autorId, SeccionesSoap $secciones, DateTimeImmutable $ahora): self
    {
        return new self($id, $expedienteId, $episodioId, $autorId, $secciones, 1, self::BORRADOR, $ahora, null);
    }

    /**
     * Semana 6: la versión siguiente de una nota firmada. La original no se toca (R-07) ni se borra (R-08).
     * Hereda expediente, episodio y autor; nace en borrador y se firma con el flujo de siempre.
     */
    public static function corregir(self $anterior, string $id, SeccionesSoap $secciones, string $motivo, DateTimeImmutable $ahora): self
    {
        if ($anterior->estado !== self::FIRMADA) {
            throw new NotaNoFirmada('Solo se corrige una nota firmada; un borrador todavía se puede firmar.');
        }
        $motivo = trim($motivo);
        if (preg_match_all('/./su', $motivo) < self::MOTIVO_MINIMO) { // caracteres, no bytes (°, tildes)
            throw new MotivoCorreccionInsuficiente(sprintf('Explique el motivo de la corrección (al menos %d caracteres).', self::MOTIVO_MINIMO));
        }

        return new self($id, $anterior->expedienteId, $anterior->episodioId, $anterior->autorId, $secciones,
            $anterior->version + 1, self::BORRADOR, $ahora, null, $anterior->id, $motivo);
    }

    public function firmar(DateTimeImmutable $momento): self
    {
        if ($this->estado === self::FIRMADA) {
            throw new NotaFirmada('La nota ya está firmada y no se puede modificar.');
        }

        return new self($this->id, $this->expedienteId, $this->episodioId, $this->autorId, $this->secciones, $this->version,
            self::FIRMADA, $this->creadaEn, $momento, $this->reemplazaA, $this->motivoCorreccion);
    }
}
