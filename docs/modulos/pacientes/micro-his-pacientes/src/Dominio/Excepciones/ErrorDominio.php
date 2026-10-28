<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Excepciones;

/**
 * Regla de negocio incumplida. El dominio no conoce HTTP: solo dice de qué CATEGORÍA
 * es el error, y Presentación decide el código de estado.
 */
class ErrorDominio extends \DomainException
{
    public const INVALIDO = 'invalido';
    public const CONFLICTO = 'conflicto';
    public const NO_ENCONTRADO = 'no_encontrado';
    public const PROHIBIDO = 'prohibido';

    /** @param array<string, mixed> $detalle */
    public function __construct(
        public readonly string $categoria,
        public readonly string $codigo,
        public readonly array $detalle = [],
    ) {
        parent::__construct($codigo);
    }
}
