<?php
declare(strict_types=1);

namespace Catalogo\Domain\Errores;

/** Base de los errores que el dominio y sus puertos pueden lanzar. */
abstract class ErrorDominio extends \RuntimeException
{
    /** @param array<string, string> $detalles */
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly array $detalles = [],
        ?\Throwable $previa = null,
    ) {
        parent::__construct($mensaje, 0, $previa);
    }
}
