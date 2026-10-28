<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Excepciones;

/** La base de datos no respondió o rechazó la operación. El caso de uso responde «no disponible», sin detalles. */
final class ErrorPersistencia extends \RuntimeException
{
    public function __construct(string $mensaje, public readonly ?string $sqlState = null, ?\Throwable $previa = null)
    {
        parent::__construct($mensaje, 0, $previa);
    }
}
