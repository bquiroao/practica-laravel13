<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http\Formularios;

use E2e\Presentacion\Http\Solicitud;

final class FormularioEscenario extends Formulario
{
    /** @return array<string, mixed> */
    public static function desde(Solicitud $s): array
    {
        $d = self::objeto($s);
        self::tipos($d, ['id' => 'texto', 'titulo' => 'texto', 'modulo' => 'texto', 'critico' => 'logico', 'requisitos' => 'lista', 'datos' => 'texto'],
            ['id', 'titulo', 'modulo', 'critico', 'requisitos', 'datos']);

        return $d;
    }
}
