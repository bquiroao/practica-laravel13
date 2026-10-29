<?php
declare(strict_types=1);

namespace Alergias\Dominio;

/** RN-03: ya existe una alergia activa equivalente. */
final class AlergiaDuplicada extends ReglaDominioViolada
{
    public function __construct()
    {
        parent::__construct('ALERGIA_DUPLICADA', 'El paciente ya tiene esta alergia activa.');
    }
}
