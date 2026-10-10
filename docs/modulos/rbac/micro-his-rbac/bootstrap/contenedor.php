<?php

declare(strict_types=1);

/*
 * Raíz de composición: el único lugar que conoce las clases concretas de todas
 * las capas y las conecta. Las pruebas usan construirKernel() con dobles; la
 * aplicación real usa crearKernel() con PDO.
 */

use Rbac\Aplicacion\CasosDeUso\AsignarRol;
use Rbac\Aplicacion\CasosDeUso\AutorizarOperacion;
use Rbac\Aplicacion\CasosDeUso\ConsultarEventoAuditoria;
use Rbac\Aplicacion\Puertos\Transaccion;
use Rbac\Dominio\Puertos\CatalogoPermisos;
use Rbac\Dominio\Puertos\DirectorioUsuarios;
use Rbac\Dominio\Puertos\RegistroAuditoria;
use Rbac\Dominio\Puertos\RepositorioMembresias;
use Rbac\Dominio\Reglas\Asignacion\ReglaNoAutoasignacion;
use Rbac\Dominio\Reglas\Asignacion\ReglaRolEnCatalogo;
use Rbac\Dominio\Reglas\Asignacion\ReglaSinMembresiaDuplicada;
use Rbac\Dominio\Reglas\Asignacion\ReglaUsuarioDestinoActivo;
use Rbac\Dominio\Reglas\Asignacion\ReglaVigenciaValida;
use Rbac\Dominio\Reglas\Autorizacion\ReglaMembresiaVigente;
use Rbac\Dominio\Reglas\Autorizacion\ReglaRolConcedePermiso;
use Rbac\Dominio\Reglas\Autorizacion\ReglaTenantCoincide;
use Rbac\Dominio\Servicios\PoliticaAsignacion;
use Rbac\Dominio\Servicios\PoliticaAutorizacion;
use Rbac\Persistencia\Archivo\RegistroAuditoriaArchivo;
use Rbac\Persistencia\Pdo\FabricaConexion;
use Rbac\Persistencia\Pdo\PdoCatalogoPermisos;
use Rbac\Persistencia\Pdo\PdoDirectorioUsuarios;
use Rbac\Persistencia\Pdo\PdoRegistroAuditoria;
use Rbac\Persistencia\Pdo\PdoRepositorioMembresias;
use Rbac\Persistencia\Pdo\PdoTransaccion;
use Rbac\Presentacion\Http\Autenticacion\Autenticador;
use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Presentacion\Http\Kernel;
use Rbac\Presentacion\Http\MiddlewareAutorizacion;
use Rbac\Presentacion\Http\TablaRutas;

require_once __DIR__ . '/autoload.php';

/**
 * Dominio + Aplicación a partir de implementaciones de los puertos.
 *
 * @return array{autorizar: AutorizarOperacion, asignarRol: AsignarRol, consultar: ConsultarEventoAuditoria}
 */
function construirCasosDeUso(
    RepositorioMembresias $membresias,
    CatalogoPermisos $catalogo,
    DirectorioUsuarios $usuarios,
    RegistroAuditoria $auditoria,
    RegistroAuditoria $auditoriaRespaldo,
    Transaccion $transaccion,
    \Closure $generarCorrelacion,
    \Closure $reloj,
): array {
    $politicaAutorizacion = new PoliticaAutorizacion([
        new ReglaTenantCoincide(),
        new ReglaMembresiaVigente(),
        new ReglaRolConcedePermiso($catalogo),
    ]);
    $politicaAsignacion = new PoliticaAsignacion([
        new ReglaUsuarioDestinoActivo($usuarios),
        new ReglaRolEnCatalogo($catalogo),
        new ReglaNoAutoasignacion(),
        new ReglaVigenciaValida(),
        new ReglaSinMembresiaDuplicada($membresias),
    ]);

    $autorizar = new AutorizarOperacion($politicaAutorizacion, $membresias, $auditoria, $auditoriaRespaldo, $generarCorrelacion, $reloj);

    return [
        'autorizar' => $autorizar,
        'asignarRol' => new AsignarRol($autorizar, $politicaAsignacion, $membresias, $auditoria, $transaccion, $generarCorrelacion, $reloj),
        'consultar' => new ConsultarEventoAuditoria($auditoria),
    ];
}

/**
 * Presentación sobre los casos de uso.
 *
 * @param array{autorizar: AutorizarOperacion, asignarRol: AsignarRol, consultar: ConsultarEventoAuditoria} $casos
 */
function construirKernel(array $casos, Autenticador $autenticador): Kernel
{
    return new Kernel(
        TablaRutas::crear($casos['asignarRol'], $casos['consultar']),
        $autenticador,
        new MiddlewareAutorizacion($casos['autorizar']),
    );
}

/**
 * Kernel de producción: PostgreSQL vía PDO + respaldo en archivo.
 *
 * @param array{db: array{dsn: string, usuario: string, clave: string}, respaldo_auditoria: string, tokens_demo: array<string, array{0: string, 1: string}>} $config
 */
function crearKernel(array $config): Kernel
{
    $pdo = FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave']);

    $directorioRespaldo = dirname($config['respaldo_auditoria']);
    if (!is_dir($directorioRespaldo)) {
        @mkdir($directorioRespaldo, 0775, true);
    }

    $casos = construirCasosDeUso(
        new PdoRepositorioMembresias($pdo),
        new PdoCatalogoPermisos($pdo),
        new PdoDirectorioUsuarios($pdo),
        new PdoRegistroAuditoria($pdo),
        new RegistroAuditoriaArchivo($config['respaldo_auditoria']),
        new PdoTransaccion($pdo),
        generarUuid(...),
        static fn (): \DateTimeImmutable => new \DateTimeImmutable(),
    );

    return construirKernel($casos, new AutenticadorTokenDemo($config['tokens_demo']));
}

/** UUID v4 para el correlation_id. */
function generarUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
