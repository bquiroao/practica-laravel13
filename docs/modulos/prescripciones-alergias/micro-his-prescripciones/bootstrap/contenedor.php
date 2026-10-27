<?php

declare(strict_types=1);

/*
 * Raíz de composición: el ÚNICO lugar que conoce las clases concretas de las cuatro capas.
 * Las pruebas arman el mismo grafo con dobles; public/index.php lo arma con PDO.
 */

require __DIR__ . '/autoload.php';

use Prescripciones\Aplicacion\CasosDeUso\ConsultarPrescripcion;
use Prescripciones\Aplicacion\CasosDeUso\EmitirPrescripcion;
use Prescripciones\Aplicacion\CasosDeUso\VerificarAlergiasPrevia;
use Prescripciones\Aplicacion\Puertos\CatalogoMedicamentos;
use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\Pacientes;
use Prescripciones\Aplicacion\Puertos\PoliticaAcceso;
use Prescripciones\Aplicacion\Puertos\RegistroAuditoria;
use Prescripciones\Aplicacion\Puertos\Transaccion;
use Prescripciones\Aplicacion\Servicios\EvaluacionClinica;
use Prescripciones\Dominio\Puertos\Reloj;
use Prescripciones\Dominio\Puertos\RepositorioPrescripciones;
use Prescripciones\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Prescripciones\Persistencia\Configuracion\RelojSistema;
use Prescripciones\Persistencia\Pdo\FabricaConexion;
use Prescripciones\Persistencia\Pdo\PdoCatalogoMedicamentos;
use Prescripciones\Persistencia\Pdo\PdoFuenteAlergias;
use Prescripciones\Persistencia\Pdo\PdoPacientes;
use Prescripciones\Persistencia\Pdo\PdoRegistroAuditoria;
use Prescripciones\Persistencia\Pdo\PdoRepositorioPrescripciones;
use Prescripciones\Persistencia\Pdo\PdoTransaccion;
use Prescripciones\Presentacion\Http\Acciones\AccionesPrescripciones;
use Prescripciones\Presentacion\Http\Autenticacion\Autenticador;
use Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Prescripciones\Presentacion\Http\Kernel;
use Prescripciones\Presentacion\Http\Ruta;

/**
 * @param array{acceso: PoliticaAcceso, pacientes: Pacientes, catalogo: CatalogoMedicamentos, alergias: FuenteAlergias,
 *              repositorio: RepositorioPrescripciones, auditoria: RegistroAuditoria, transaccion: Transaccion, reloj: Reloj} $p
 * @return array{previa: VerificarAlergiasPrevia, emitir: EmitirPrescripcion, consultar: ConsultarPrescripcion}
 */
function construirCasosDeUso(array $p): array
{
    $evaluacion = new EvaluacionClinica($p['catalogo'], $p['alergias']);

    return [
        'previa' => new VerificarAlergiasPrevia($p['acceso'], $p['pacientes'], $evaluacion),
        'emitir' => new EmitirPrescripcion($p['acceso'], $p['pacientes'], $evaluacion, $p['repositorio'], $p['auditoria'], $p['transaccion'], $p['reloj']),
        'consultar' => new ConsultarPrescripcion($p['acceso'], $p['repositorio']),
    ];
}

/** @param array{previa: VerificarAlergiasPrevia, emitir: EmitirPrescripcion, consultar: ConsultarPrescripcion} $casos */
function construirKernel(array $casos, Autenticador $autenticador): Kernel
{
    $acciones = new AccionesPrescripciones($casos['previa'], $casos['emitir'], $casos['consultar']);
    $base = '/api/v1/prescripciones';

    return new Kernel([
        new Ruta('POST', "#^$base/verificaciones$#", $acciones->verificar(...)),
        new Ruta('POST', "#^$base$#", $acciones->emitir(...)),
        new Ruta('GET', "#^$base/(?<id>[1-9][0-9]{0,17})$#", $acciones->consultar(...)),
    ], $autenticador);
}

/** @param array<string, mixed> $config @return array<string, object> los puertos con sus adaptadores PDO */
function puertosPdo(\PDO $pdo, array $config): array
{
    return [
        'acceso' => new PoliticaAccesoEstatica($config['permisos']),
        'pacientes' => new PdoPacientes($pdo),
        'catalogo' => new PdoCatalogoMedicamentos($pdo),
        'alergias' => new PdoFuenteAlergias($pdo),
        'repositorio' => new PdoRepositorioPrescripciones($pdo),
        'auditoria' => new PdoRegistroAuditoria($pdo),
        'transaccion' => new PdoTransaccion($pdo),
        'reloj' => new RelojSistema(),
    ];
}

/** Punto de entrada real: PostgreSQL con PDO. */
function crearKernel(array $config): Kernel
{
    $pdo = FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave']);

    return construirKernel(construirCasosDeUso(puertosPdo($pdo, $config)), new AutenticadorTokenDemo($config['tokens_demo']));
}
