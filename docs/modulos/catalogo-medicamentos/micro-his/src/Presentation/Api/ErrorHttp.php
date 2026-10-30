<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Api;

/** Errores propios del protocolo HTTP (401, 403, 400, 415...), distintos de los del dominio. */
final class ErrorHttp extends \RuntimeException
{
    /** @param array<string, string> $cabeceras */
    public function __construct(public readonly int $estado, public readonly string $codigo, string $mensaje, public readonly array $cabeceras = [])
    {
        parent::__construct($mensaje);
    }
}
