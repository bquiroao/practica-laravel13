<?php
declare(strict_types=1);

namespace Alergias\Dominio\Reglas;

use Alergias\Dominio\Alergia;
use Alergias\Dominio\NivelAlerta;

/** Punto de extensión OCP (semana 2): una regla nueva es una clase nueva. */
interface ReglaNivelAlerta
{
    /** Devuelve el nivel que esta regla asigna, o null si no aplica. */
    public function nivelPara(Alergia $alergia): ?NivelAlerta;
}
