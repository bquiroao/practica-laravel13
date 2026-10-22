<?php

declare(strict_types=1);

/*
 * Raíz de composición: el único lugar que conoce las clases concretas de todas las capas
 * y las conecta. Elige los adaptadores (PDO o InMemory) con PACIENTES_PERSISTENCIA; el resto
 * del sistema no sabe cuál se eligió.
 */

use Pacientes\Aplicacion\CasosDeUso\ActualizarPaciente;
use Pacientes\Aplicacion\CasosDeUso\BuscarPacientes;
use Pacientes\Aplicacion\CasosDeUso\ConsultarPaciente;
use Pacientes\Aplicacion\CasosDeUso\RegistrarPaciente;
use Pacientes\Aplicacion\Comandos\ComandoRegistrar;
use Pacientes\Aplicacion\Puertos\PoliticaAcceso;
use Pacientes\Aplicacion\Puertos\Reloj;
use Pacientes\Aplicacion\Servicios\ControlAcceso;
use Pacientes\Dominio\Servicios\PoliticaConfidencialidad;
use Pacientes\Dominio\Servicios\PoliticaDuplicados;
use Pacientes\Dominio\Servicios\PoliticaEdicion;
use Pacientes\Dominio\Servicios\RevisionDeFicha;
use Pacientes\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Pacientes\Persistencia\Configuracion\RelojSistema;
use Pacientes\Persistencia\ConjuntoAdaptadores;
use Pacientes\Persistencia\FabricaEnMemoria;
use Pacientes\Persistencia\FabricaPdo;
use Pacientes\Persistencia\Seguridad\ProteccionDocumentos;
use Pacientes\Presentacion\Http\Autenticacion\Autenticador;
use Pacientes\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Pacientes\Presentacion\Http\Controladores\PacientesControlador;
use Pacientes\Presentacion\Http\Kernel;
use Pacientes\Presentacion\Http\Observabilidad\RegistroAcceso;
use Pacientes\Presentacion\Http\Observabilidad\RegistroAccesoJson;
use Pacientes\Presentacion\Http\Rutas;

require_once __DIR__ . '/autoload.php';

/**
 * Dominio + Aplicación (el Modelo de MVC) sobre un juego de adaptadores.
 *
 * @return array{registrar: RegistrarPaciente, buscar: BuscarPacientes, consultar: ConsultarPaciente, actualizar: ActualizarPaciente}
 */
function construirCasosDeUso(ConjuntoAdaptadores $a, PoliticaAcceso $politica, Reloj $reloj): array
{
    $acceso = new ControlAcceso($politica);
    // Semana 7: las tres políticas de la ficha se componen en un solo servicio de Dominio.
    $revision = new RevisionDeFicha(new PoliticaEdicion(), new PoliticaDuplicados($a->indice), new PoliticaConfidencialidad());

    return [
        'registrar' => new RegistrarPaciente($a->repositorio, $revision, $a->auditoria, $a->transaccion, $acceso, $reloj, $a->idempotencia),
        'buscar' => new BuscarPacientes($a->consulta, $a->auditoria, $acceso),
        'consultar' => new ConsultarPaciente($a->consulta, $a->auditoria, $acceso),
        'actualizar' => new ActualizarPaciente($a->repositorio, $revision, $a->auditoria, $a->transaccion, $acceso, $reloj),
    ];
}

/** Vista + Controlador de MVC sobre el Modelo. */
function construirKernel(array $casos, Autenticador $autenticador, ?RegistroAcceso $registroAcceso = null): Kernel
{
    $controlador = new PacientesControlador($casos['registrar'], $casos['buscar'], $casos['consultar'], $casos['actualizar']);

    return new Kernel(Rutas::definir($controlador), $autenticador, $registroAcceso);
}

/**
 * @param array<string, mixed> $config
 * @throws \Pacientes\Dominio\Excepciones\ErrorPersistencia sin conexión
 * @throws InvalidArgumentException si faltan las claves del documento (modo pdo)
 */
function crearAdaptadores(array $config): ConjuntoAdaptadores
{
    return match ($config['persistencia']) {
        'memoria' => FabricaEnMemoria::crear(),
        'pdo' => FabricaPdo::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave'],
            ProteccionDocumentos::desdeBase64($config['claves']['hash'], $config['claves']['cifrado'])),
        default => throw new InvalidArgumentException('PACIENTES_PERSISTENCIA debe ser pdo o memoria.'),
    };
}

/** @param array<string, mixed> $config */
function crearCasosDeUso(array $config): array
{
    $casos = construirCasosDeUso(crearAdaptadores($config), new PoliticaAccesoEstatica($config['permisos']), new RelojSistema());
    if ($config['persistencia'] === 'memoria') {
        // En memoria cada proceso empieza vacío: se cargan los pacientes ficticios de demostración.
        sembrarDemo($casos);
    }

    return $casos;
}

/** @param array<string, mixed> $config */
function crearKernel(array $config): Kernel
{
    return construirKernel(crearCasosDeUso($config), new AutenticadorTokenDemo($config['tokens_demo']), new RegistroAccesoJson());
}

/** Registra los pacientes de database/datos-demo.php a través del caso de uso. */
function sembrarDemo(array $casos): array
{
    $resultados = [];
    foreach (require dirname(__DIR__) . '/database/datos-demo.php' as [$actor, $hospital, $datos]) {
        $resultados[] = [$hospital, $datos, $casos['registrar']->ejecutar(new ComandoRegistrar($actor, $hospital, $datos))];
    }

    return $resultados;
}
