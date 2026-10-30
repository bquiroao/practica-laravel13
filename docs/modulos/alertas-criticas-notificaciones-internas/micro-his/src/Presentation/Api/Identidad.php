<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation\Api;

/** Quién llama a la API: el tenant SIEMPRE sale del token, nunca del cuerpo ni de la URL. */
final class Identidad
{
    public function __construct(public readonly string $usuario, public readonly string $rol, public readonly string $tenant) {}
}
