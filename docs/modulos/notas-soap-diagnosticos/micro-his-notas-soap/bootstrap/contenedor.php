<?php

declare(strict_types=1);

/*
 * Raíz de composición: el único lugar que conoce las clases concretas de todas las capas.
 *   construirKernel()      arma el servidor con el conjunto de adaptadores que se le pase.
 *   adaptadoresPdo()       adaptadores PDO contra PostgreSQL.
 *   crearKernel()          lo que usa public/index.php: PDO, o memoria si NOTASSOAP_ALMACEN=memoria.
 *   crearKernelEnMemoria() adaptadores en memoria con los datos de demostración (demo, medición y pruebas).
 * Cambiar de PDO a memoria NO toca ningún controlador, caso de uso ni entidad.
 */

use NotasSoap\Aplicacion\CasosDeUso\ConsultarNota;
use NotasSoap\Aplicacion\CasosDeUso\CorregirNota;
use NotasSoap\Aplicacion\CasosDeUso\FirmarNota;
use NotasSoap\Aplicacion\CasosDeUso\ListarNotasExpediente;
use NotasSoap\Aplicacion\CasosDeUso\RegistrarNotaConDiagnostico;
use NotasSoap\Aplicacion\Integracion\RepositorioNotasConEventos;
use NotasSoap\Aplicacion\Puertos\BandejaSalida;
use NotasSoap\Aplicacion\Puertos\ConjuntoAdaptadores;
use NotasSoap\Aplicacion\Puertos\GeneradorId;
use NotasSoap\Aplicacion\Puertos\Reloj;
use NotasSoap\Persistencia\EnMemoria\AlmacenesEnMemoria;
use NotasSoap\Persistencia\Pdo\FabricaConexion;
use NotasSoap\Persistencia\Pdo\PdoBandejaSalida;
use NotasSoap\Persistencia\Pdo\PdoRegistroAuditoria;
use NotasSoap\Persistencia\Pdo\PdoRegistroIdempotencia;
use NotasSoap\Persistencia\Pdo\PdoRepositorioDiagnosticos;
use NotasSoap\Persistencia\Pdo\PdoRepositorioExpedientes;
use NotasSoap\Persistencia\Pdo\PdoRepositorioNotas;
use NotasSoap\Persistencia\Pdo\PdoTransaccion;
use NotasSoap\Persistencia\Pdo\PdoVerificadorAcceso;
use NotasSoap\Persistencia\Sistema\GeneradorUuid;
use NotasSoap\Persistencia\Sistema\RelojSistema;
use NotasSoap\Presentacion\Controladores\NotasSoapController;
use NotasSoap\Presentacion\Http\Autenticador;
use NotasSoap\Presentacion\Http\AutenticadorTokenDemo;
use NotasSoap\Presentacion\Http\Autorizacion\PermisosPorRol;
use NotasSoap\Presentacion\Http\Autorizacion\PoliticaPermisos;
use NotasSoap\Presentacion\Http\Kernel;
use NotasSoap\Presentacion\Http\Observabilidad\RegistroAcceso;
use NotasSoap\Presentacion\Http\Observabilidad\RegistroAccesoEnMemoria;
use NotasSoap\Presentacion\Http\Observabilidad\RegistroAccesoErrorLog;
use NotasSoap\Presentacion\Http\Rutas;
use NotasSoap\Presentacion\Vistas\SelectorVista;
use NotasSoap\Presentacion\Vistas\VistaHtml;
use NotasSoap\Presentacion\Vistas\VistaJson;

require_once __DIR__ . '/autoload.php';

function construirKernel(
    Autenticador $autenticador,
    ConjuntoAdaptadores $a,
    GeneradorId $ids,
    Reloj $reloj,
    PoliticaPermisos $permisos,
    RegistroAcceso $registroAcceso,
): Kernel {
    $vistas = new SelectorVista(new VistaJson(), new VistaHtml());
    $controlador = new NotasSoapController(
        new RegistrarNotaConDiagnostico($a->acceso, $a->expedientes, $a->notas, $a->diagnosticos, $a->auditoria, $a->transaccion, $ids, $reloj, $a->idempotencia),
        new FirmarNota($a->notas, $a->diagnosticos, $a->auditoria, $a->transaccion, $reloj),
        new ConsultarNota($a->notas, $a->diagnosticos),
        new ListarNotasExpediente($a->notas, $a->diagnosticos),
        new CorregirNota($a->acceso, $a->expedientes, $a->notas, $a->diagnosticos, $a->auditoria, $a->transaccion, $ids, $reloj),
        $vistas,
    );

    return new Kernel($autenticador, Rutas::definir($controlador), $vistas, $permisos, $registroAcceso);
}

/** Envuelve el repositorio de notas para que la firma deje su evento en la bandeja de salida (semana 4). */
function conBandejaDeSalida(ConjuntoAdaptadores $a, BandejaSalida $bandeja, GeneradorId $ids): ConjuntoAdaptadores
{
    return $a->conNotas(new RepositorioNotasConEventos($a->notas, $bandeja, $ids));
}

function adaptadoresPdo(\PDO $pdo, GeneradorId $ids): ConjuntoAdaptadores
{
    return conBandejaDeSalida(new ConjuntoAdaptadores(
        new PdoVerificadorAcceso($pdo),
        new PdoRepositorioExpedientes($pdo),
        new PdoRepositorioNotas($pdo),
        new PdoRepositorioDiagnosticos($pdo),
        new PdoRegistroAuditoria($pdo),
        new PdoTransaccion($pdo),
        new PdoRegistroIdempotencia($pdo),
    ), new PdoBandejaSalida($pdo), $ids);
}

/** @param array<string, mixed> $config */
function crearKernel(array $config): Kernel
{
    $ids = new GeneradorUuid();
    $reloj = new RelojSistema();
    if (($config['almacen'] ?? 'pdo') === 'memoria') {
        $datos = require dirname(__DIR__) . '/config/datos-demo-memoria.php';
        $archivo = $config['memoria_archivo'] ?? null;
        if ($archivo === null || $archivo === '') {
            return crearKernelEnMemoria($config, new AlmacenesEnMemoria($datos), $ids, $reloj, new RegistroAccesoErrorLog());
        }
        $almacenes = almacenesDesdeArchivo($archivo, $datos);
        register_shutdown_function(static fn () => guardarAlmacenes($archivo, $almacenes));

        return crearKernelEnMemoria($config, $almacenes, $ids, $reloj, new RegistroAccesoErrorLog());
    }
    $pdo = FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave']);

    return construirKernel(new AutenticadorTokenDemo($config['tokens_demo']), adaptadoresPdo($pdo, $ids), $ids, $reloj,
        new PermisosPorRol($config['permisos_por_rol']), new RegistroAccesoErrorLog());
}

/** @param array<string, mixed> $config */
function crearKernelEnMemoria(array $config, AlmacenesEnMemoria $almacenes, GeneradorId $ids, Reloj $reloj, ?RegistroAcceso $registroAcceso = null): Kernel
{
    return construirKernel(
        new AutenticadorTokenDemo($config['tokens_demo']),
        conBandejaDeSalida($almacenes->conjunto(), $almacenes->bandeja, $ids),
        $ids,
        $reloj,
        new PermisosPorRol($config['permisos_por_rol']),
        $registroAcceso ?? new RegistroAccesoEnMemoria(),
    );
}

/*
 * Modo memoria con archivo (solo para demostración).
 * `php -S` atiende cada petición en un proceso nuevo: sin archivo, lo guardado se pierde entre peticiones.
 * Con NOTASSOAP_MEMORIA_ARCHIVO los almacenes se leen al empezar y se guardan al terminar cada petición.
 * No es para producción ni para medir: el archivo lo define quien levanta el servidor y no se comparte.
 */

/** @param array{expedientes: array<string, array{paciente: string, abierto: bool}>, episodios: array<string, string>, asignaciones: array<string, list<string>>} $datos */
function almacenesDesdeArchivo(string $archivo, array $datos): AlmacenesEnMemoria
{
    if (is_file($archivo)) {
        $guardado = unserialize((string) file_get_contents($archivo));
        if ($guardado instanceof AlmacenesEnMemoria) {
            return $guardado;
        }
    }

    return new AlmacenesEnMemoria($datos);
}

function guardarAlmacenes(string $archivo, AlmacenesEnMemoria $almacenes): void
{
    file_put_contents($archivo, serialize($almacenes), LOCK_EX);
}
