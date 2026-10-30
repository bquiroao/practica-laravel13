<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http\Formularios;

use E2e\Presentacion\Http\Solicitud;

/** Versión candidata, plan, inicio de ejecución y evaluación del gate: los formularios de la ruta /versiones. */
final class FormularioVersion extends Formulario
{
    /** @return array<string, mixed> */
    public static function registrar(Solicitud $s): array
    {
        $d = self::objeto($s);
        self::tipos($d, ['numero' => 'texto', 'commit' => 'texto', 'digest' => 'texto', 'modulos' => 'lista'], ['numero', 'commit', 'digest', 'modulos']);

        return $d;
    }

    /** @return list<mixed> */
    public static function plan(Solicitud $s): array
    {
        $d = self::objeto($s);
        self::tipos($d, ['escenarios' => 'lista'], ['escenarios']);

        return $d['escenarios'];
    }

    /** @return array{digest: string, entorno: string} */
    public static function ejecucion(Solicitud $s): array
    {
        $d = self::objeto($s);
        self::tipos($d, ['digest' => 'texto', 'entorno' => 'texto'], ['digest', 'entorno']);

        return ['digest' => $d['digest'], 'entorno' => $d['entorno']];
    }

    /** @return array<string, string> semana 6: digest, entorno, guia y smoke */
    public static function despliegue(Solicitud $s): array
    {
        $d = self::objeto($s);
        self::tipos($d, ['digest' => 'texto', 'entorno' => 'texto', 'guia' => 'texto', 'smoke' => 'texto'], ['digest', 'entorno', 'guia', 'smoke']);

        return $d;
    }

    public static function gate(Solicitud $s): int
    {
        $d = self::objeto($s);
        self::tipos($d, ['ejecucion' => 'entero'], ['ejecucion']);

        return $d['ejecucion'];
    }
}
