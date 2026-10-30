<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http\Controladores;

/** La tabla de rutas: método, patrón (con parámetros con nombre) y acción del controlador. */
final class Rutas
{
    public const BASE = '/api/v1/e2e';
    private const VERSION = '(?<version>\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?)';

    /** @return list<array{0: string, 1: string, 2: string, 3: string}> [método, patrón, controlador, acción] */
    public static function tabla(): array
    {
        $v = self::VERSION;

        return [
            ['POST', '/escenarios', 'escenarios', 'registrar'],
            ['POST', '/versiones', 'versiones', 'registrar'],
            ['POST', "/versiones/$v/plan", 'versiones', 'planificar'],
            ['POST', "/versiones/$v/ejecuciones", 'versiones', 'iniciarEjecucion'],
            ['POST', "/versiones/$v/gate", 'versiones', 'evaluarGate'],
            ['POST', "/versiones/$v/gate/vista-previa", 'versiones', 'previsualizarGate'],   // semana 12, B-01
            ['POST', "/versiones/$v/despliegues", 'versiones', 'desplegar'],
            ['GET', "/versiones/$v/trazabilidad", 'versiones', 'trazabilidad'],
            ['POST', '/ejecuciones/(?<id>\d+)/resultados', 'ejecuciones', 'reportar'],
            ['POST', '/ejecuciones/(?<id>\d+)/cierre', 'ejecuciones', 'cerrar'],
        ];
    }
}
