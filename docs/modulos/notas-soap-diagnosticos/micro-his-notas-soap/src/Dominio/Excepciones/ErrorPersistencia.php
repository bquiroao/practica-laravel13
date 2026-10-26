<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Excepciones;

/** Falla técnica de almacenamiento. Las capas superiores solo conocen esta excepción, nunca PDOException. */
final class ErrorPersistencia extends \RuntimeException
{
    public function __construct(string $mensaje, public readonly ?string $sqlState = null, ?\Throwable $causa = null)
    {
        parent::__construct($mensaje, 0, $causa);
    }
}
