<?php

declare(strict_types=1);

/*
 * Raíz de composición: el ÚNICO lugar que conoce las clases concretas de las cuatro capas.
 * public/index.php la usa con PDO o en memoria (E2E_PERSISTENCIA); las pruebas arman el mismo grafo con adaptadores en memoria.
 */

require __DIR__ . '/autoload.php';

if (!extension_loaded('mbstring')) {
    throw new \RuntimeException('Micro-HIS E2E necesita la extensión mbstring de PHP.');
}

use E2e\Aplicacion\CasosDeUso\CerrarEjecucion;
use E2e\Aplicacion\CasosDeUso\ConsultarTrazabilidad;
use E2e\Aplicacion\CasosDeUso\EvaluarGate;
use E2e\Aplicacion\CasosDeUso\IniciarEjecucion;
use E2e\Aplicacion\CasosDeUso\PlanificarPruebas;
use E2e\Aplicacion\CasosDeUso\RegistrarEscenario;
use E2e\Aplicacion\CasosDeUso\RegistrarVersion;
use E2e\Aplicacion\CasosDeUso\ReportarResultado;
use E2e\Dominio\Modelo\PoliticaGate;
use E2e\Persistencia\FabricaAdaptadores;
use E2e\Presentacion\Http\Kernel;

/** @param array<string, object> $p puertos @return array<string, object> */
function construirCasosDeUso(array $p, array $politica): array
{
    $gate = new PoliticaGate($politica['version'], $politica['umbral_no_criticos'], $politica['max_inestables'], $politica['antiguedad_maxima_horas']);

    return [
        'escenario' => new RegistrarEscenario($p['acceso'], $p['escenarios'], $p['auditoria'], $p['transaccion']),
        'version' => new RegistrarVersion($p['acceso'], $p['versiones'], $p['auditoria'], $p['transaccion']),
        'plan' => new PlanificarPruebas($p['acceso'], $p['escenarios'], $p['versiones'], $p['auditoria'], $p['transaccion']),
        'iniciar' => new IniciarEjecucion($p['acceso'], $p['versiones'], $p['ejecuciones'], $p['auditoria'], $p['transaccion'], $p['reloj']),
        'reportar' => new ReportarResultado($p['acceso'], $p['ejecuciones'], $p['almacen'], $p['auditoria'], $p['transaccion'], $p['reloj']),
        'cerrar' => new CerrarEjecucion($p['acceso'], $p['ejecuciones'], $p['auditoria'], $p['transaccion'], $p['reloj']),
        'gate' => new EvaluarGate($p['acceso'], $p['versiones'], $p['ejecuciones'], $p['evaluaciones'], $p['almacen'], $p['auditoria'], $p['transaccion'],
            $p['reloj'], $gate),
        'trazabilidad' => new ConsultarTrazabilidad($p['acceso'], $p['versiones'], $p['ejecuciones'], $p['evaluaciones']),
    ];
}

function crearKernel(array $config): Kernel
{
    return new Kernel(construirCasosDeUso(FabricaAdaptadores::crear($config), $config['politica_gate']), $config['tokens_demo']);
}
