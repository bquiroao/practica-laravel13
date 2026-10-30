<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Excepciones;

/** Los parámetros de una consulta no se pueden interpretar. Lleva el código y los campos que fallaron. */
final class ConsultaInvalida extends \DomainException
{
    public const FILTRO_NO_SOPORTADO = 'FILTRO_NO_SOPORTADO';
    public const DATOS_INVALIDOS = 'DATOS_INVALIDOS';

    /** @param array<string, string> $campos */
    public function __construct(public readonly string $codigo, public readonly array $campos)
    {
        parent::__construct($codigo);
    }
}
