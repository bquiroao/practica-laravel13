<?php
declare(strict_types=1);

namespace Catalogo\Domain\Errores;

/** El código no existe en el hospital (tenant) del usuario. La presentación lo traduce a 404. */
final class ErrorNoEncontrado extends ErrorDominio
{
    public function __construct(string $codigo)
    {
        parent::__construct('NO_ENCONTRADO', "No existe el medicamento $codigo en este hospital", ['codigo' => $codigo]);
    }
}
