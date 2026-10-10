<?php

declare(strict_types=1);

namespace NotasSoap\Dominio;

use NotasSoap\Dominio\Excepciones\DiagnosticoInvalido;

/** R-05: diagnóstico CIE-10 válido. Única razón de cambio: qué es un diagnóstico válido. */
final class Diagnostico
{
    private const FORMATO_CIE10 = '/^[A-Z][0-9]{2}(\.[0-9]{1,2})?$/';

    private function __construct(
        public readonly ?string $id,
        public readonly ?string $notaId,
        public readonly string $codigo,
        public readonly string $descripcion,
        public readonly TipoDiagnostico $tipo,
    ) {
    }

    public static function registrar(string $codigo, string $descripcion, string $tipo): self
    {
        $codigo = strtoupper(trim($codigo));
        if (preg_match(self::FORMATO_CIE10, $codigo) !== 1) {
            throw new DiagnosticoInvalido(sprintf('El código "%s" no tiene formato CIE-10 (ejemplo: J18.9).', $codigo));
        }
        if (trim($descripcion) === '') {
            throw new DiagnosticoInvalido('La descripción del diagnóstico no puede estar vacía.');
        }
        $tipoValido = TipoDiagnostico::tryFrom($tipo);
        if ($tipoValido === null) {
            throw new DiagnosticoInvalido(sprintf('El tipo "%s" no es válido (primary, secondary o differential).', $tipo));
        }

        return new self(null, null, $codigo, trim($descripcion), $tipoValido);
    }

    public function paraNota(string $notaId): self
    {
        return new self($this->id, $notaId, $this->codigo, $this->descripcion, $this->tipo);
    }

    public function conId(string $id): self
    {
        return new self($id, $this->notaId, $this->codigo, $this->descripcion, $this->tipo);
    }
}
