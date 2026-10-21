<?php

declare(strict_types=1);

/*
 * Raíz de composición: el único lugar que conoce las clases concretas de todas las capas
 * y las conecta. Las pruebas usan construirCasosDeUso() con dobles; la aplicación real
 * usa crearKernel() con PDO.
 */

use Pacientes\Aplicacion\CasosDeUso\ActualizarPaciente;
use Pacientes\Aplicacion\CasosDeUso\BuscarPacientes;
use Pacientes\Aplicacion\CasosDeUso\ConsultarPaciente;
use Pacientes\Aplicacion\CasosDeUso\RegistrarPaciente;
use Pacientes\Aplicacion\Puertos\PoliticaAcceso;
use Pacientes\Aplicacion\Puertos\Reloj;
use Pacientes\Aplicacion\Puertos\Transaccion;
use Pacientes\Aplicacion\Servicios\ControlAcceso;
use Pacientes\Dominio\Puertos\ConsultaPacientes;
use Pacientes\Dominio\Puertos\IndiceDuplicados;
use Pacientes\Dominio\Puertos\RegistroAuditoria;
use Pacientes\Dominio\Puertos\RepositorioPacientes;
use Pacientes\Dominio\Servicios\PoliticaDuplicados;
use Pacientes\Dominio\Servicios\PoliticaEdicion;
use Pacientes\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Pacientes\Persistencia\Configuracion\RelojSistema;
use Pacientes\Persistencia\Pdo\FabricaConexion;
use Pacientes\Persistencia\Pdo\PdoIndiceDuplicados;
use Pacientes\Persistencia\Pdo\PdoRegistroAuditoria;
use Pacientes\Persistencia\Pdo\PdoRepositorioPacientes;
use Pacientes\Persistencia\Pdo\PdoTransaccion;
use Pacientes\Persistencia\Seguridad\ProteccionDocumentos;
use Pacientes\Presentacion\Http\Autenticacion\Autenticador;
use Pacientes\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Pacientes\Presentacion\Http\Kernel;
use Pacientes\Presentacion\Http\TablaRutas;

require_once __DIR__ . '/autoload.php';

/**
 * Dominio + Aplicación a partir de implementaciones de los puertos.
 * La búsqueda recibe ConsultaPacientes: puede ser el repositorio o una réplica de lectura.
 *
 * @return array{registrar: RegistrarPaciente, buscar: BuscarPacientes, consultar: ConsultarPaciente, actualizar: ActualizarPaciente}
 */
function construirCasosDeUso(
    ConsultaPacientes $lectura,
    RepositorioPacientes $repositorio,
    IndiceDuplicados $indice,
    RegistroAuditoria $auditoria,
    Transaccion $transaccion,
    PoliticaAcceso $politica,
    Reloj $reloj,
): array {
    $acceso = new ControlAcceso($politica);
    $duplicados = new PoliticaDuplicados($indice);

    return [
        'registrar' => new RegistrarPaciente($repositorio, $duplicados, $auditoria, $transaccion, $acceso, $reloj),
        'buscar' => new BuscarPacientes($lectura, $auditoria, $acceso),
        'consultar' => new ConsultarPaciente($lectura, $auditoria, $acceso),
        'actualizar' => new ActualizarPaciente($repositorio, new PoliticaEdicion(), $duplicados, $auditoria, $transaccion, $acceso, $reloj),
    ];
}

/** @param array{registrar: RegistrarPaciente, buscar: BuscarPacientes, consultar: ConsultarPaciente, actualizar: ActualizarPaciente} $casos */
function construirKernel(array $casos, Autenticador $autenticador): Kernel
{
    return new Kernel(TablaRutas::crear($casos['registrar'], $casos['buscar'], $casos['consultar'], $casos['actualizar']), $autenticador);
}

/**
 * Casos de uso sobre PostgreSQL.
 *
 * @param array{db: array{dsn: string, usuario: string, clave: string}, claves: array{hash: string, cifrado: string}, permisos: array<string, list<string>>} $config
 * @return array{registrar: RegistrarPaciente, buscar: BuscarPacientes, consultar: ConsultarPaciente, actualizar: ActualizarPaciente}
 */
function crearCasosDeUsoPdo(array $config): array
{
    $proteccion = ProteccionDocumentos::desdeBase64($config['claves']['hash'], $config['claves']['cifrado']);
    $pdo = FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave']);
    $repositorio = new PdoRepositorioPacientes($pdo, $proteccion);

    return construirCasosDeUso(
        $repositorio,
        $repositorio,
        new PdoIndiceDuplicados($pdo, $proteccion),
        new PdoRegistroAuditoria($pdo),
        new PdoTransaccion($pdo),
        new PoliticaAccesoEstatica($config['permisos']),
        new RelojSistema(),
    );
}

/** @param array<string, mixed> $config */
function crearKernel(array $config): Kernel
{
    return construirKernel(crearCasosDeUsoPdo($config), new AutenticadorTokenDemo($config['tokens_demo']));
}
