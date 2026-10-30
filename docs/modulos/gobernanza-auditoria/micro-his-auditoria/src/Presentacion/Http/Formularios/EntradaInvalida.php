<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http\Formularios;

/** La petición no tiene el FORMATO esperado. Es distinto de una regla de negocio rota (eso lo decide el Dominio). */
final class EntradaInvalida extends \RuntimeException
{
    public const CUERPO = 'CUERPO_INVALIDO';
    public const FORMATO = 'FORMATO_INVALIDO';

    /** @param array<string, string> $campos */
    public function __construct(public readonly string $tipo, public readonly array $campos = [])
    {
        parent::__construct($tipo);
    }
}
