<?php
declare(strict_types=1);

namespace Alergias\Dominio\Reglas;

use Alergias\Dominio\Alergia;
use Alergias\Dominio\NivelAlerta;

/** Regla agregada sin tocar el calculador (OCP): una reacción anafiláctica siempre es CRITICA. */
final class ReglaReaccionAnafilactica implements ReglaNivelAlerta
{
    public function nivelPara(Alergia $alergia): ?NivelAlerta
    {
        $reaccion = mb_strtolower($alergia->reaccion ?? '');
        return str_contains($reaccion, 'anafila') ? NivelAlerta::Critica : null;
    }
}
