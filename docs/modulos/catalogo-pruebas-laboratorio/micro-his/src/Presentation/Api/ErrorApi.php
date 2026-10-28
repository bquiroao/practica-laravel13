<?php
declare(strict_types=1);

namespace Lab\Presentation\Api;

/** Error ya traducido a estado HTTP y código del contrato. */
final class ErrorApi extends \RuntimeException
{
    public function __construct(
        public readonly int $estado,
        public readonly string $codigo,
        string $mensaje,
        public readonly array $detalle = [],
    ) {
        parent::__construct($mensaje);
    }
}
