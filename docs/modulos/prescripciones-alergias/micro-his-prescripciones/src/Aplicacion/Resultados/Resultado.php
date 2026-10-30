<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\Resultados;

/**
 * Resultado de un caso de uso, sin HTTP: la Presentación traduce el tipo a un código de estado.
 */
final readonly class Resultado
{
    public const CREADO = 'CREADO';
    public const OK = 'OK';
    public const RECHAZADO = 'RECHAZADO';
    public const CONFLICTO = 'CONFLICTO';
    public const NO_ENCONTRADO = 'NO_ENCONTRADO';
    public const DENEGADO = 'DENEGADO';
    public const NO_DISPONIBLE = 'NO_DISPONIBLE';

    /** @param array<string, mixed> $datos */
    public function __construct(public string $tipo, public string $codigo, public array $datos = [])
    {
    }
}
