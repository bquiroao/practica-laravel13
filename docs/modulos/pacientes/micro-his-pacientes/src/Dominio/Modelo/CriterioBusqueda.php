<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Modelo;

use Pacientes\Dominio\Excepciones\DatosInvalidos;
use Pacientes\Dominio\Excepciones\ErrorDominio;

/**
 * Criterio de búsqueda ya validado (R-10): código, documento completo o nombre (3 letras
 * o más) con fecha de nacimiento opcional (AAAA, AAAA-MM o AAAA-MM-DD).
 */
final class CriterioBusqueda
{
    public const LIMITE = 20;
    public const POR_CODIGO = 'codigo';
    public const POR_DOCUMENTO = 'documento';
    public const POR_NOMBRE = 'nombre';

    private function __construct(
        public readonly string $tipo,
        public readonly ?string $codigo = null,
        public readonly ?Identificador $documento = null,
        public readonly ?string $nombre = null,
        public readonly ?string $nacimiento = null,
        /** R-11: por defecto, la búsqueda por NOMBRE no devuelve pacientes protegidos. */
        public readonly bool $incluirProtegidos = false,
    ) {
    }

    /** Copia del criterio que incluye (o no) a los protegidos en la búsqueda por nombre. */
    public function incluyendoProtegidos(bool $incluir): self
    {
        return new self($this->tipo, $this->codigo, $this->documento, $this->nombre, $this->nacimiento, $incluir);
    }

    /** @throws ErrorDominio CRITERIO_INSUFICIENTE o DatosInvalidos */
    public static function desde(?string $codigo = null, ?string $tipoDocumento = null, ?string $numeroDocumento = null,
        ?string $paisDocumento = null, ?string $nombre = null, ?string $nacimiento = null): self
    {
        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo !== '') {
            return new self(self::POR_CODIGO, codigo: $codigo);
        }

        try {
            $documento = FabricaIdentificador::crear($tipoDocumento, $numeroDocumento, $paisDocumento);
        } catch (\InvalidArgumentException $e) {
            throw new DatosInvalidos(['documento' => $e->getMessage()]);
        }
        if ($documento !== null) {
            return new self(self::POR_DOCUMENTO, documento: $documento);
        }

        $nombre = Texto::normalizar((string) $nombre);
        $nacimiento = trim((string) $nacimiento);
        if (Texto::contarLetras($nombre) < 3) {
            throw new ErrorDominio(ErrorDominio::INVALIDO, 'CRITERIO_INSUFICIENTE',
                ['ayuda' => 'Use el código, el documento completo o al menos 3 letras del nombre.']);
        }
        if ($nacimiento !== '' && preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $nacimiento) !== 1) {
            throw new DatosInvalidos(['nacimiento' => 'Use AAAA, AAAA-MM o AAAA-MM-DD.']);
        }

        return new self(self::POR_NOMBRE, nombre: $nombre, nacimiento: $nacimiento === '' ? null : $nacimiento);
    }

    /** La regla de coincidencia, para implementaciones en memoria (en SQL se traduce a WHERE). */
    public function coincideCon(Paciente $paciente): bool
    {
        return match ($this->tipo) {
            self::POR_CODIGO => $paciente->codigo === $this->codigo,
            self::POR_DOCUMENTO => $paciente->identificador?->canonico() === $this->documento?->canonico(),
            default => ($this->incluirProtegidos || !$paciente->identidadProtegida)
                && str_contains($paciente->nombreNormalizado(), (string) $this->nombre)
                && ($this->nacimiento === null || str_starts_with($paciente->nacimiento->format('Y-m-d'), $this->nacimiento)),
        };
    }

    /** Para la auditoría: QUÉ tipo de criterio se usó, sin datos personales en claro. */
    public function resumenAuditable(): string
    {
        return match ($this->tipo) {
            self::POR_NOMBRE => sprintf('nombre(%d letras)%s', Texto::contarLetras((string) $this->nombre), $this->nacimiento !== null ? '+nacimiento' : ''),
            default => $this->tipo,
        };
    }
}
