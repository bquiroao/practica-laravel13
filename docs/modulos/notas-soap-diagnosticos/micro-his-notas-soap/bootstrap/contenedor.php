<?php

declare(strict_types=1);

/*
 * Raíz de composición: el único lugar que conoce las clases concretas de todas las capas.
 * Las pruebas usan construirKernel() con dobles; la aplicación real usa crearKernel() con PDO.
 */

use NotasSoap\Aplicacion\CasosDeUso\ConsultarNota;
use NotasSoap\Aplicacion\CasosDeUso\FirmarNota;
use NotasSoap\Aplicacion\CasosDeUso\RegistrarNotaConDiagnostico;
use NotasSoap\Aplicacion\Puertos\GeneradorId;
use NotasSoap\Aplicacion\Puertos\Reloj;
use NotasSoap\Aplicacion\Puertos\Transaccion;
use NotasSoap\Dominio\Puertos\RegistroAuditoria;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioExpedientes;
use NotasSoap\Dominio\Puertos\RepositorioNotas;
use NotasSoap\Dominio\Puertos\VerificadorAcceso;
use NotasSoap\Persistencia\Pdo\FabricaConexion;
use NotasSoap\Persistencia\Pdo\PdoRegistroAuditoria;
use NotasSoap\Persistencia\Pdo\PdoRepositorioDiagnosticos;
use NotasSoap\Persistencia\Pdo\PdoRepositorioExpedientes;
use NotasSoap\Persistencia\Pdo\PdoRepositorioNotas;
use NotasSoap\Persistencia\Pdo\PdoTransaccion;
use NotasSoap\Persistencia\Pdo\PdoVerificadorAcceso;
use NotasSoap\Persistencia\Sistema\GeneradorUuid;
use NotasSoap\Persistencia\Sistema\RelojSistema;
use NotasSoap\Presentacion\Http\Autenticador;
use NotasSoap\Presentacion\Http\AutenticadorTokenDemo;
use NotasSoap\Presentacion\Http\Kernel;
use NotasSoap\Presentacion\Http\NotasSoapController;

require_once __DIR__ . '/autoload.php';

function construirKernel(
    Autenticador $autenticador,
    VerificadorAcceso $acceso,
    RepositorioExpedientes $expedientes,
    RepositorioNotas $notas,
    RepositorioDiagnosticos $diagnosticos,
    RegistroAuditoria $auditoria,
    Transaccion $transaccion,
    GeneradorId $ids,
    Reloj $reloj,
): Kernel {
    return new Kernel($autenticador, new NotasSoapController(
        new RegistrarNotaConDiagnostico($acceso, $expedientes, $notas, $diagnosticos, $auditoria, $transaccion, $ids, $reloj),
        new FirmarNota($notas, $auditoria, $transaccion, $reloj),
        new ConsultarNota($notas, $diagnosticos),
    ));
}

/** @param array{db: array{dsn: string, usuario: string, clave: string}, tokens_demo: array<string, array{0: string, 1: string, 2: list<string>}>} $config */
function crearKernel(array $config): Kernel
{
    $pdo = FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave']);

    return construirKernel(
        new AutenticadorTokenDemo($config['tokens_demo']),
        new PdoVerificadorAcceso($pdo),
        new PdoRepositorioExpedientes($pdo),
        new PdoRepositorioNotas($pdo),
        new PdoRepositorioDiagnosticos($pdo),
        new PdoRegistroAuditoria($pdo),
        new PdoTransaccion($pdo),
        new GeneradorUuid(),
        new RelojSistema(),
    );
}
