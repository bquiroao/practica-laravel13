<?php

declare(strict_types=1);

/*
 * Raíz de composición: el ÚNICO lugar que conoce las clases concretas de las cuatro capas.
 * public/index.php la usa con PDO o en memoria (QA_PERSISTENCIA); las pruebas arman el mismo grafo con adaptadores
 * en memoria y decoradores que inyectan fallas (tests/Dobles).
 */

require __DIR__ . '/autoload.php';

// Semana 15 (RT-07): el Dominio usa mbstring (mb_strlen, mb_check_encoding). Sin ella, diseñar un caso terminaba en un error
// fatal en medio de una petición; ahora el arranque lo dice antes. pdo_pgsql se verifica al abrir la conexión (FabricaConexion).
if (!extension_loaded('mbstring')) {
    throw new \RuntimeException('Micro-HIS QA necesita la extensión mbstring de PHP (ver README, «Requisitos»).');
}

use Qa\Aplicacion\CasosDeUso\AprobarCaso;
use Qa\Aplicacion\CasosDeUso\CerrarCampana;
use Qa\Aplicacion\CasosDeUso\ClasificarDefecto;
use Qa\Aplicacion\CasosDeUso\ConsultarTrazabilidad;
use Qa\Aplicacion\CasosDeUso\DisenarCaso;
use Qa\Aplicacion\CasosDeUso\IniciarCampana;
use Qa\Aplicacion\CasosDeUso\MarcarCorregido;
use Qa\Aplicacion\CasosDeUso\PlanificarCampana;
use Qa\Aplicacion\CasosDeUso\RegistrarResultado;
use Qa\Persistencia\FabricaAdaptadores;
use Qa\Presentacion\Http\Autenticacion\Autenticador;
use Qa\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Qa\Presentacion\Http\Controladores\CampanasControlador;
use Qa\Presentacion\Http\Controladores\CasosControlador;
use Qa\Presentacion\Http\Controladores\DefectosControlador;
use Qa\Presentacion\Http\Controladores\Rutas;
use Qa\Presentacion\Http\Kernel;
use Qa\Presentacion\Http\Observabilidad\RegistroAcceso;
use Qa\Presentacion\Http\Observabilidad\RegistroAccesoJson;

/**
 * @param array{acceso: \Qa\Aplicacion\Puertos\PoliticaAcceso, casos: \Qa\Dominio\Puertos\RepositorioCasos,
 *              campanas: \Qa\Dominio\Puertos\RepositorioCampanas, defectos: \Qa\Dominio\Puertos\RepositorioDefectos,
 *              almacen: \Qa\Aplicacion\Puertos\AlmacenEvidencias, auditoria: \Qa\Aplicacion\Puertos\RegistroAuditoria,
 *              transaccion: \Qa\Aplicacion\Puertos\Transaccion, reloj: \Qa\Dominio\Puertos\Reloj} $p
 * @return array<string, object>
 */
function construirCasosDeUso(array $p): array
{
    return [
        'disenar' => new DisenarCaso($p['acceso'], $p['casos'], $p['auditoria'], $p['transaccion']),
        'aprobar' => new AprobarCaso($p['acceso'], $p['casos'], $p['auditoria'], $p['transaccion']),
        'planificar' => new PlanificarCampana($p['acceso'], $p['casos'], $p['campanas'], $p['auditoria'], $p['transaccion']),
        'iniciar' => new IniciarCampana($p['acceso'], $p['casos'], $p['campanas'], $p['auditoria'], $p['transaccion']),
        'registrar' => new RegistrarResultado($p['acceso'], $p['campanas'], $p['defectos'], $p['almacen'], $p['auditoria'], $p['transaccion'], $p['reloj']),
        'clasificar' => new ClasificarDefecto($p['acceso'], $p['defectos'], $p['auditoria'], $p['transaccion']),
        'corregir' => new MarcarCorregido($p['acceso'], $p['defectos'], $p['auditoria'], $p['transaccion']),
        'cerrar' => new CerrarCampana($p['acceso'], $p['casos'], $p['campanas'], $p['defectos'], $p['auditoria'], $p['transaccion']),
        'trazabilidad' => new ConsultarTrazabilidad($p['acceso'], $p['casos'], $p['campanas'], $p['defectos']),
    ];
}

/** @param array<string, object> $c casos de uso */
function construirKernel(array $c, Autenticador $autenticador, ?RegistroAcceso $registro = null): Kernel
{
    return new Kernel(Rutas::definir(
        new CasosControlador($c['disenar'], $c['aprobar']),
        new CampanasControlador($c['planificar'], $c['iniciar'], $c['registrar'], $c['cerrar'], $c['trazabilidad']),
        new DefectosControlador($c['clasificar'], $c['corregir']),
    ), $autenticador, $registro);
}

/** Punto de entrada real: PostgreSQL con PDO, o todo en memoria (QA_PERSISTENCIA=memoria). */
function crearKernel(array $config): Kernel
{
    return construirKernel(construirCasosDeUso(FabricaAdaptadores::crear($config)), new AutenticadorTokenDemo($config['tokens_demo']),
        ($config['registro_acceso'] ?? 'stderr') === 'ninguno' ? null : new RegistroAccesoJson());
}
