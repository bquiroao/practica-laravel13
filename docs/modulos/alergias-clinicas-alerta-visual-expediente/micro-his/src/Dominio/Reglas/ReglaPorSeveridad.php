<?php
declare(strict_types=1);

namespace Alergias\Dominio\Reglas;

use Alergias\Dominio\Alergia;
use Alergias\Dominio\NivelAlerta;
use Alergias\Dominio\Severidad;

/** RN-08: la severidad desconocida se trata como grave. */
final class ReglaPorSeveridad implements ReglaNivelAlerta
{
    public function nivelPara(Alergia $alergia): ?NivelAlerta
    {
        return match ($alergia->severidad) {
            Severidad::PoneEnRiesgoLaVida => NivelAlerta::Critica,
            Severidad::Grave, Severidad::Desconocida => NivelAlerta::Alta,
            Severidad::Moderada => NivelAlerta::Moderada,
            Severidad::Leve => NivelAlerta::Informativa,
        };
    }
}
