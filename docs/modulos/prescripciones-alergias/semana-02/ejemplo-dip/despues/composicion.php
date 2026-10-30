<?php

declare(strict_types=1);

/*
 * Raíz de composición: el ÚNICO lugar que conoce las clases concretas y decide qué adaptador
 * satisface cada puerto. Cambiar la fuente de alergias (memoria, PDO, otra API) se hace aquí,
 * sin tocar EmitirPrescripcion ni el dominio.
 */

use Prescripciones\Adaptadores\EnMemoria\AuditoriaEnMemoria;
use Prescripciones\Adaptadores\EnMemoria\CatalogoEnMemoria;
use Prescripciones\Adaptadores\EnMemoria\FuenteAlergiasEnMemoria;
use Prescripciones\Adaptadores\EnMemoria\PacientesEnMemoria;
use Prescripciones\Adaptadores\EnMemoria\PoliticaEnMemoria;
use Prescripciones\Adaptadores\EnMemoria\RelojFijo;
use Prescripciones\Adaptadores\EnMemoria\RepositorioEnMemoria;
use Prescripciones\Aplicacion\EmitirPrescripcion;
use Prescripciones\Aplicacion\EvaluacionClinica;
use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\VerificarAlergiasPrevia;
use Prescripciones\Dominio\Alergia;
use Prescripciones\Dominio\Medicamento;

/** Datos de demostración (ficticios). Los códigos de principio activo siguen la clasificación ATC. */
function catalogoDemo(): CatalogoEnMemoria
{
    return new CatalogoEnMemoria([
        new Medicamento('AMOX500', 'Amoxicilina 500 mg', 'J01CA04', 'Amoxicilina', 'betalactamicos', 'mg', 3000, ['oral'], true),
        new Medicamento('CEFA500', 'Cefalexina 500 mg', 'J01DB01', 'Cefalexina', 'betalactamicos', 'mg', 4000, ['oral'], true),
        new Medicamento('PARA500', 'Paracetamol 500 mg', 'N02BE01', 'Paracetamol', 'anilidas', 'mg', 4000, ['oral', 'rectal'], true),
        new Medicamento('IBU400', 'Ibuprofeno 400 mg', 'M01AE01', 'Ibuprofeno', 'aines', 'mg', 2400, ['oral'], true),
        new Medicamento('RANI150', 'Ranitidina 150 mg', 'A02BA02', 'Ranitidina', 'antih2', 'mg', 300, ['oral'], false),
    ], [
        'J01CA04' => 'betalactamicos',
        'J01CE01' => 'betalactamicos',
        'penicilina' => 'betalactamicos',
        'N02BA01' => 'aines',
        'M01AE01' => 'aines',
    ]);
}

/** @return array<string, list<Alergia>> paciente => alergias, con los valores de la tabla real `allergies` */
function alergiasDemo(): array
{
    return [
        'P-001' => [new Alergia('J01CA04', 'Amoxicilina', 'allergy', 'severe', 'active', 'urticaria generalizada')],
        'P-002' => [new Alergia(null, 'Penicilina', 'allergy', 'moderate', 'active', 'exantema')],
        // P-003: sin ningún registro (alergias no evaluadas)
        'P-004' => [new Alergia('N02BA01', 'Ácido acetilsalicílico', 'allergy', 'life_threatening', 'active', 'anafilaxia')],
        'P-005' => [new Alergia('J01CA04', 'Amoxicilina', 'allergy', 'mild', 'resolved', null)],
        'P-006' => [new Alergia(null, 'penicilina', 'unknown', 'unknown', 'active', null)],
        'P-007' => [new Alergia(null, '  AMOXICILINA ', 'allergy', 'moderate', 'active', 'prurito')],
    ];
}

function pacientesDemo(): PacientesEnMemoria
{
    return new PacientesEnMemoria([
        'hosp-norte' => ['P-001', 'P-002', 'P-003', 'P-004', 'P-005', 'P-006', 'P-007'],
        'hosp-sur' => ['P-900'],
    ]);
}

function politicaDemo(): PoliticaEnMemoria
{
    return new PoliticaEnMemoria([
        'dr-lopez' => ['hosp-norte' => ['prescripcion.emitir', 'prescripcion.override']],
        'dr-ruiz' => ['hosp-norte' => ['prescripcion.emitir']],
        'dr-sur' => ['hosp-sur' => ['prescripcion.emitir', 'prescripcion.override']],
    ]);
}

/**
 * Arma el módulo. Por omisión, todo en memoria; quien llama puede pasar otra fuente de alergias
 * (la PDO, un doble caído o cualquier otra implementación del puerto).
 *
 * @return array{emitir: EmitirPrescripcion, previa: VerificarAlergiasPrevia, repositorio: RepositorioEnMemoria, auditoria: AuditoriaEnMemoria, fuente: FuenteAlergias}
 */
function componer(?FuenteAlergias $fuente = null): array
{
    $fuente ??= new FuenteAlergiasEnMemoria(alergiasDemo());
    $politica = politicaDemo();
    $pacientes = pacientesDemo();
    $evaluacion = new EvaluacionClinica(catalogoDemo(), $fuente);
    $repositorio = new RepositorioEnMemoria();
    $auditoria = new AuditoriaEnMemoria();

    return [
        'emitir' => new EmitirPrescripcion($politica, $pacientes, $evaluacion, $repositorio, $auditoria, new RelojFijo()),
        'previa' => new VerificarAlergiasPrevia($politica, $pacientes, $evaluacion),
        'repositorio' => $repositorio,
        'auditoria' => $auditoria,
        'fuente' => $fuente,
    ];
}
