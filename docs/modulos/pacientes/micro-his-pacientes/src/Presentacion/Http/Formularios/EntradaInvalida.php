<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Formularios;

/** La petición no tiene el FORMATO esperado (no es una regla de negocio). */
final class EntradaInvalida extends \InvalidArgumentException
{
    public const CUERPO = 'CUERPO_INVALIDO';
    public const FORMATO = 'FORMATO_INVALIDO';
    public const VERSION_REQUERIDA = 'VERSION_REQUERIDA';
    public const VERSION_INVALIDA = 'IF_MATCH_INVALIDO';

    /** @param array<string, string> $campos */
    public function __construct(public readonly string $tipo, public readonly array $campos = [])
    {
        parent::__construct($tipo);
    }
}
