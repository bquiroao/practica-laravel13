<?php
declare(strict_types=1);

namespace Alergias\Dominio;

use DomainException;

/** Error de negocio con un código estable (se usa igual en consola, MVC y API). */
class ReglaDominioViolada extends DomainException
{
    public function __construct(public readonly string $codigo, string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
