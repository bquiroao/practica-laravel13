<?php
declare(strict_types=1);

namespace Catalogo\Domain\Errores;

/** Datos con formato o límites inválidos (RN-05). La presentación lo traduce a 422. */
final class ErrorValidacion extends ErrorDominio
{
    /** @param array<string, string> $errores campo => mensaje */
    public function __construct(array $errores)
    {
        parent::__construct('VALIDACION', 'Hay datos inválidos', $errores);
    }
}
