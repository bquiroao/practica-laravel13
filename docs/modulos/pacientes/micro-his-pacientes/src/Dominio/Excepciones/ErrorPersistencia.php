<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Excepciones;

/**
 * Falla al leer o escribir datos. Los puertos del dominio solo prometen esta
 * excepción; ningún detalle de PDO o SQL cruza hacia las capas superiores.
 */
final class ErrorPersistencia extends \RuntimeException
{
    public function __construct(string $mensaje, public readonly ?string $sqlState = null, ?\Throwable $previa = null)
    {
        parent::__construct($mensaje, 0, $previa);
    }
}
