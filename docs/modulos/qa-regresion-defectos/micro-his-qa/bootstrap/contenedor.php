<?php

declare(strict_types=1);

/*
 * Raíz de composición: el ÚNICO lugar que conoce las clases concretas de las cuatro capas.
 * public/index.php lo arma con PDO; las pruebas arman el mismo grafo con dobles (tests/Dobles).
 */

require __DIR__ . '/autoload.php';

use Qa\Aplicacion\CasosDeUso\AprobarCaso;
use Qa\Aplicacion\CasosDeUso\CerrarCampana;
use Qa\Aplicacion\CasosDeUso\ClasificarDefecto;
use Qa\Aplicacion\CasosDeUso\ConsultarTrazabilidad;
use Qa\Aplicacion\CasosDeUso\DisenarCaso;
use Qa\Aplicacion\CasosDeUso\IniciarCampana;
use Qa\Aplicacion\CasosDeUso\MarcarCorregido;
use Qa\Aplicacion\CasosDeUso\PlanificarCampana;
use Qa\Aplicacion\CasosDeUso\RegistrarResultado;
use Qa\Persistencia\Archivos\AlmacenEvidenciasArchivos;
use Qa\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Qa\Persistencia\Configuracion\RelojSistema;
use Qa\Persistencia\Pdo\FabricaConexion;
use Qa\Persistencia\Pdo\PdoRegistroAuditoria;
use Qa\Persistencia\Pdo\PdoRepositorioCampanas;
use Qa\Persistencia\Pdo\PdoRepositorioCasos;
use Qa\Persistencia\Pdo\PdoRepositorioDefectos;
use Qa\Persistencia\Pdo\PdoTransaccion;
use Qa\Presentacion\Http\Acciones\AccionesQa;
use Qa\Presentacion\Http\Autenticacion\Autenticador;
use Qa\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Qa\Presentacion\Http\Kernel;

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
        'cerrar' => new CerrarCampana($p['acceso'], $p['campanas'], $p['defectos'], $p['auditoria'], $p['transaccion']),
        'trazabilidad' => new ConsultarTrazabilidad($p['acceso'], $p['casos'], $p['campanas'], $p['defectos']),
    ];
}

/** @param array<string, object> $casos */
function construirKernel(array $casos, Autenticador $autenticador): Kernel
{
    return new Kernel((new AccionesQa($casos))->rutas(), $autenticador);
}

/** @return array<string, object> los puertos con sus adaptadores reales (PostgreSQL + disco) */
function adaptadoresPdo(array $config, ?\PDO $pdo = null): array
{
    $pdo ??= FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave']);

    return [
        'acceso' => new PoliticaAccesoEstatica($config['permisos']),
        'casos' => new PdoRepositorioCasos($pdo),
        'campanas' => new PdoRepositorioCampanas($pdo),
        'defectos' => new PdoRepositorioDefectos($pdo),
        'almacen' => new AlmacenEvidenciasArchivos($config['evidencias']),
        'auditoria' => new PdoRegistroAuditoria($pdo),
        'transaccion' => new PdoTransaccion($pdo),
        'reloj' => new RelojSistema(),
    ];
}

/** Punto de entrada real. */
function crearKernel(array $config): Kernel
{
    return construirKernel(construirCasosDeUso(adaptadoresPdo($config)), new AutenticadorTokenDemo($config['tokens_demo']));
}
