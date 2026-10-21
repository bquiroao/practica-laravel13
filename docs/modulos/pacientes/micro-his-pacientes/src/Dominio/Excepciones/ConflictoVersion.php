<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Excepciones;

/** R-06: la versión leída ya no es la vigente; otro usuario modificó al paciente. */
final class ConflictoVersion extends ErrorDominio
{
    public function __construct(int $versionVigente)
    {
        parent::__construct(self::CONFLICTO, 'VERSION_DESACTUALIZADA', ['version_vigente' => $versionVigente]);
    }
}
