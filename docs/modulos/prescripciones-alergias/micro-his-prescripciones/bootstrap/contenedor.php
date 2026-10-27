<?php

declare(strict_types=1);

/*
 * Raíz de composición: el ÚNICO lugar que conoce las clases concretas de las cuatro capas.
 * Las pruebas arman el mismo grafo con los adaptadores en memoria (y decoradores que inyectan fallas);
 * public/index.php lo arma con PDO o en memoria según PRESCRIPCIONES_PERSISTENCIA.
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
use Prescripciones\Persistencia\FabricaAdaptadores;
use Prescripciones\Presentacion\Http\Autenticacion\Autenticador;
use Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Prescripciones\Presentacion\Http\Controladores\PrescripcionesControlador;
use Prescripciones\Presentacion\Http\Kernel;
use Prescripciones\Presentacion\Http\Observabilidad\RegistroAcceso;
use Prescripciones\Presentacion\Http\Observabilidad\RegistroAccesoJson;
use Prescripciones\Presentacion\Http\Rutas;

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
function construirKernel(array $casos, Autenticador $autenticador, ?RegistroAcceso $registro = null): Kernel
{
    $controlador = new PrescripcionesControlador($casos['previa'], $casos['emitir'], $casos['consultar']);

    return new Kernel(Rutas::definir($controlador), $autenticador, $registro);
}

/** Punto de entrada real: PostgreSQL con PDO, o todo en memoria (PRESCRIPCIONES_PERSISTENCIA=memoria). */
function crearKernel(array $config): Kernel
{
    return construirKernel(construirCasosDeUso(FabricaAdaptadores::crear($config)), new AutenticadorTokenDemo($config['tokens_demo']),
        ($config['registro_acceso'] ?? 'stderr') === 'ninguno' ? null : new RegistroAccesoJson());
}
