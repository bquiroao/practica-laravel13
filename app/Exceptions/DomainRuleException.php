<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Regla de negocio rechazada: código estable para el cliente (p. ej. AUTOASIGNACION) y su estado HTTP. */
class DomainRuleException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode], $this->status);
    }
}
