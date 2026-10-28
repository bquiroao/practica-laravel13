<?php

declare(strict_types=1);

namespace Pacientes\Dominio;

/** La versión enviada ya no es la vigente: otro usuario modificó al paciente (R-06). */
final class ConflictoVersion extends ErrorPaciente
{
    public function __construct(int $versionVigente)
    {
        parent::__construct(409, 'VERSION_DESACTUALIZADA', ['version_vigente' => $versionVigente]);
    }
}
