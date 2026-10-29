<?php
declare(strict_types=1);

namespace Admision\Domain\Excepciones;

final class CamaNoEncontrada extends ErrorDeDominio
{
    public function __construct(string $mensaje = 'La cama no existe en este hospital.')
    {
        parent::__construct($mensaje);
    }

    public function codigo(): string
    {
        return 'CAMA_NO_ENCONTRADA';
    }
}
