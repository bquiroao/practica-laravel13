<?php

declare(strict_types=1);

/*
 * Raíz de composición: el único lugar que conoce las clases concretas de todas
 * las capas y las conecta. Elige los adaptadores (PDO o InMemory) según la
 * configuración; el resto del sistema no se entera de cuál se usa.
 */

use Rbac\Aplicacion\CasosDeUso\AsignarRol;
use Rbac\Aplicacion\CasosDeUso\AutorizarOperacion;
use Rbac\Aplicacion\CasosDeUso\ConsultarEventoAuditoria;
use Rbac\Aplicacion\CasosDeUso\RevocarRol;
use Rbac\Aplicacion\Servicios\RegistradorDeCambios;
use Rbac\Dominio\Puertos\RegistroAuditoria;
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
use Rbac\Dominio\Servicios\PoliticaRevocacion;
use Rbac\Dominio\Reglas\Revocacion\ReglaMembresiaRevocable;
use Rbac\Dominio\Reglas\Revocacion\ReglaMotivoSuficiente;
use Rbac\Dominio\Reglas\Revocacion\ReglaNoAutorrevocacion;
use Rbac\Persistencia\Archivo\RegistroAuditoriaArchivo;
use Rbac\Persistencia\ConjuntoAdaptadores;
use Rbac\Persistencia\EnMemoria\FabricaEnMemoria;
use Rbac\Persistencia\Pdo\FabricaConexion;
use Rbac\Persistencia\Pdo\FabricaPdo;
use Rbac\Presentacion\Http\Autenticacion\Autenticador;
use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Presentacion\Http\Controladores\AsignacionRolControlador;
use Rbac\Presentacion\Http\Controladores\AuditoriaControlador;
use Rbac\Presentacion\Http\Controladores\AutorizacionControlador;
use Rbac\Presentacion\Http\Controladores\OperacionClinicaControlador;
use Rbac\Presentacion\Http\Controladores\RevocacionRolControlador;
use Rbac\Presentacion\Http\Kernel;
use Rbac\Presentacion\Http\MiddlewareAutorizacion;
use Rbac\Presentacion\Http\Rutas;

require_once __DIR__ . '/autoload.php';

/**
 * Adaptadores según la configuración: 'pdo' (PostgreSQL) o 'memoria' (datos semilla).
 *
 * @param array{persistencia: string, db: array{dsn: string, usuario: string, clave: string}} $config
 */
function crearAdaptadores(array $config): ConjuntoAdaptadores
{
    return match ($config['persistencia']) {
        'pdo' => FabricaPdo::crear(FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave'])),
        'memoria' => FabricaEnMemoria::crear(require dirname(__DIR__) . '/database/datos-demo.php'),
        default => throw new InvalidArgumentException('MICROHIS_PERSISTENCIA debe ser "pdo" o "memoria".'),
    };
}

/**
 * Dominio + Aplicación (el «Modelo» de MVC) sobre cualquier conjunto de adaptadores.
 *
 * @return array{autorizar: AutorizarOperacion, asignarRol: AsignarRol, consultar: ConsultarEventoAuditoria, revocarRol: RevocarRol}
 */
function construirCasosDeUso(
    ConjuntoAdaptadores $adaptadores,
    RegistroAuditoria $auditoriaRespaldo,
    \Closure $generarCorrelacion,
    \Closure $reloj,
): array {
    $politicaAutorizacion = new PoliticaAutorizacion([
        new ReglaTenantCoincide(),
        new ReglaMembresiaVigente(),
        new ReglaRolConcedePermiso($adaptadores->catalogo),
    ]);
    $politicaAsignacion = new PoliticaAsignacion([
        new ReglaUsuarioDestinoActivo($adaptadores->usuarios),
        new ReglaRolEnCatalogo($adaptadores->catalogo),
        new ReglaNoAutoasignacion(),
        new ReglaVigenciaValida(),
        new ReglaSinMembresiaDuplicada($adaptadores->membresias),
    ]);

    $autorizar = new AutorizarOperacion(
        $politicaAutorizacion,
        $adaptadores->membresias,
        $adaptadores->auditoria,
        $auditoriaRespaldo,
        $generarCorrelacion,
        $reloj,
    );

    // Semana 7: un solo servicio aplica cualquier cambio de membresía con su evidencia (R-07).
    $cambios = new RegistradorDeCambios($adaptadores->transaccion, $adaptadores->membresias, $adaptadores->auditoria);

    return [
        'autorizar' => $autorizar,
        'asignarRol' => new AsignarRol($autorizar, $politicaAsignacion, $cambios, $generarCorrelacion, $reloj),
        'consultar' => new ConsultarEventoAuditoria($adaptadores->auditoria),
        'revocarRol' => new RevocarRol(
            $autorizar,
            new PoliticaRevocacion([
                new ReglaNoAutorrevocacion(),
                new ReglaMotivoSuficiente(),
                new ReglaMembresiaRevocable($adaptadores->membresias),
            ]),
            $cambios,
            $generarCorrelacion,
            $reloj,
        ),
    ];
}

/**
 * Controladores, vistas y rutas (la «V» y la «C» de MVC) sobre los casos de uso.
 *
 * @param array{autorizar: AutorizarOperacion, asignarRol: AsignarRol, consultar: ConsultarEventoAuditoria} $casos
 */
function construirKernel(array $casos, Autenticador $autenticador, ?\Closure $registrarAcceso = null): Kernel
{
    return new Kernel(
        construirRutas($casos),
        $autenticador,
        new MiddlewareAutorizacion($casos['autorizar']),
        $registrarAcceso,
        generarUuid(...),
    );
}

/**
 * Controladores y tabla de rutas sobre los casos de uso (la usan el Kernel y las pruebas de contrato).
 *
 * @param array<string, object> $casos
 * @return list<\Rbac\Presentacion\Http\Ruta>
 */
function construirRutas(array $casos): array
{
    return Rutas::definir(
        new AsignacionRolControlador($casos['asignarRol']),
        new AuditoriaControlador($casos['consultar']),
        new OperacionClinicaControlador(),
        new AutorizacionControlador($casos['autorizar']),
        new RevocacionRolControlador($casos['revocarRol']),
    );
}

/** Registro de acceso estructurado (una línea JSON por petición) hacia el log de PHP / stderr. */
function registrarAccesoEnLog(array $registro): void
{
    error_log((string) json_encode(['tipo' => 'acceso'] + $registro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Kernel de la aplicación a partir de la configuración.
 *
 * @param array{persistencia: string, db: array{dsn: string, usuario: string, clave: string}, respaldo_auditoria: string, tokens_demo: array<string, array{0: string, 1: string}>} $config
 */
function crearKernel(array $config): Kernel
{
    $directorioRespaldo = dirname($config['respaldo_auditoria']);
    if (!is_dir($directorioRespaldo)) {
        @mkdir($directorioRespaldo, 0775, true);
    }

    $casos = construirCasosDeUso(
        crearAdaptadores($config),
        new RegistroAuditoriaArchivo($config['respaldo_auditoria']),
        generarUuid(...),
        static fn (): \DateTimeImmutable => new \DateTimeImmutable(),
    );

    return construirKernel($casos, new AutenticadorTokenDemo($config['tokens_demo']), registrarAccesoEnLog(...));
}

/** UUID v4 para el correlation_id. */
function generarUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
