<?php
declare(strict_types=1);

namespace Admision\Domain\Excepciones;

/** Falla técnica del almacenamiento. El repositorio garantiza que no quedan datos a medias (RNF-08). */
final class ErrorPersistencia extends \RuntimeException
{
    public function codigo(): string
    {
        return 'ERROR_PERSISTENCIA';
    }
}
