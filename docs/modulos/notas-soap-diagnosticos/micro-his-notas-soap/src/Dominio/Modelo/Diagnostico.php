<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Modelo;

use DateTimeImmutable;
use NotasSoap\Dominio\Excepciones\DiagnosticoInvalido;

/** R-05: diagnóstico CIE-10 válido, ligado a la nota y con el mismo autor y episodio. */
final class Diagnostico
{
    private const FORMATO_CIE10 = '/^[A-Z][0-9]{2}(\.[0-9]{1,2})?$/';

    private function __construct(
        public readonly string $id,
        public readonly string $notaId,
        public readonly string $episodioId,
        public readonly string $autorId,
        public readonly string $codigo,
        public readonly string $descripcion,
        public readonly TipoDiagnostico $tipo,
        public readonly DateTimeImmutable $fechaInicio,
    ) {
    }

    public static function registrar(string $id, NotaSoap $nota, string $codigo, string $descripcion, string $tipo): self
    {
        $codigo = strtoupper(trim($codigo));
        if (preg_match(self::FORMATO_CIE10, $codigo) !== 1) {
            throw new DiagnosticoInvalido(sprintf('El código "%s" no tiene formato CIE-10 (ejemplo: J18.9).', $codigo));
        }
        if (trim($descripcion) === '') {
            throw new DiagnosticoInvalido('La descripción del diagnóstico no puede estar vacía.');
        }
        $tipoValido = TipoDiagnostico::tryFrom($tipo)
            ?? throw new DiagnosticoInvalido(sprintf('El tipo "%s" no es válido (primary, secondary o differential).', $tipo));

        return new self($id, $nota->id, $nota->episodioId, $nota->autorId, $codigo, trim($descripcion), $tipoValido, $nota->creadaEn);
    }

    public static function reconstruir(string $id, string $notaId, string $episodioId, string $autorId, string $codigo, string $descripcion, string $tipo, DateTimeImmutable $fechaInicio): self
    {
        return new self($id, $notaId, $episodioId, $autorId, $codigo, $descripcion, TipoDiagnostico::from($tipo), $fechaInicio);
    }
}
