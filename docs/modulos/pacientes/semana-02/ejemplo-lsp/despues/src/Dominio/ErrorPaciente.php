<?php

declare(strict_types=1);

namespace Pacientes\Dominio;

/** Error de negocio previsto por el contrato: se traduce a una respuesta HTTP. */
class ErrorPaciente extends \RuntimeException
{
    /** @param array<string, mixed> $detalle */
    public function __construct(
        public readonly int $http,
        public readonly string $codigo,
        public readonly array $detalle = [],
    ) {
        parent::__construct($codigo);
    }
}
