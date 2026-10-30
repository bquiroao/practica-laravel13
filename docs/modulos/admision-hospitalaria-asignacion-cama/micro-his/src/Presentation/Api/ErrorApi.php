<?php
declare(strict_types=1);

namespace Admision\Presentation\Api;

/** Error propio de la API (autenticación o forma de la petición), con el mismo formato que los del dominio. */
final class ErrorApi extends \RuntimeException
{
    /** @param array<string, mixed> $detalles */
    public function __construct(private readonly string $codigoError, string $mensaje, public readonly array $detalles = [])
    {
        parent::__construct($mensaje);
    }

    public function codigo(): string
    {
        return $this->codigoError;
    }
}
