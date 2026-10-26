<?php

declare(strict_types=1);

namespace Pacientes\Dominio;

/** Resultado de un caso de uso, listo para convertirse en respuesta HTTP. */
final class Respuesta
{
    /** @param array<string, mixed> $cuerpo */
    public function __construct(public readonly int $http, public readonly array $cuerpo)
    {
    }

    public static function desdeError(ErrorPaciente $error): self
    {
        return new self($error->http, ['error' => $error->codigo] + $error->detalle);
    }

    public static function errorInterno(): self
    {
        return new self(500, ['error' => 'ERROR_INTERNO']);
    }

    public function error(): ?string
    {
        return $this->cuerpo['error'] ?? null;
    }
}
