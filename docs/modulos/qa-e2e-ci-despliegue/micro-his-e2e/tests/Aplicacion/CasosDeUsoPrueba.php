<?php

declare(strict_types=1);

use E2e\Aplicacion\Resultados\Salida;

prueba('APP-01', 'Camino feliz: escenario → versión → plan → ejecución → 6 resultados → completar → gate APROBADO → trazabilidad, con la traza de cada paso (R-14)', function (): void {
    $e = new Escenario();
    $nuevo = $e->uc['escenario']->ejecutar('ing-qa', 'hosp-norte', ['id' => 'E2E-107', 'titulo' => 'Dar de alta', 'modulo' => 'traslados', 'critico' => false,
        'requisitos' => ['RF-11'], 'datos' => 'SINT-ALTA-01']);
    igual([Salida::CREADO, 'ESCENARIO_REGISTRADO'], [$nuevo->tipo, $nuevo->codigo], 'escenario');
    $id = $e->ejecucionEnCurso(['E2E-101', 'E2E-102', 'E2E-103', 'E2E-104', 'E2E-105', 'E2E-106']);
    $e->completar($id);
    $e->reloj->avanzar('+2 hours');
    $g = $e->uc['gate']->ejecutar('resp-release', 'hosp-norte', '2.4.0', $id);
    igual([Salida::OK, 'APROBADO', [], 'gate-2026.1'], [$g->tipo, $g->datos['decision'], $g->datos['causas'], $g->datos['politica']], 'gate');
    $t = $e->uc['trazabilidad']->ejecutar('dev-ana', 'hosp-norte', '2.4.0');
    igual(['APROBADA', true, 1, 6, 'APROBADO'], [$t->datos['estado'], $t->datos['plan_congelado'], count($t->datos['ejecuciones']),
        count($t->datos['ejecuciones'][0]['resultados']), $t->datos['evaluaciones'][0]['decision']], 'trazabilidad');
    igual(['ESCENARIO_REGISTRADO', 'VERSION_REGISTRADA', 'PLAN_DEFINIDO', 'EJECUCION_INICIADA', ...array_fill(0, 6, 'RESULTADO_REPORTADO'),
        'EJECUCION_COMPLETADA', 'GATE_APROBADO'], $e->auditoriaReal()->acciones(), 'traza');
});

prueba('APP-02', 'Regla de dominio por el caso de uso: un crítico INESTABLE y una ejecución de 25 h → RECHAZADO con sus causas y la versión RECHAZADA', function (): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $e->completar($id, ['E2E-102' => ['PASA', 2]]);
    $e->reloj->avanzar('+25 hours');
    $g = $e->uc['gate']->ejecutar('resp-release', 'hosp-norte', '2.4.0', $id);
    igual(['RECHAZADO', ['críticos que no pasan: E2E-102', 'ejecución de 25 h (máximo 24 h)']], [$g->datos['decision'], $g->datos['causas']], 'decisión');
    igual('RECHAZADA', $e->uc['trazabilidad']->ejecutar('dev-ana', 'hosp-norte', '2.4.0')->datos['estado'], 'versión');
});

prueba('APP-03', 'R-12: la misma clave con el mismo contenido devuelve el MISMO resultado (200), aunque la ejecución ya esté completada; con otro contenido, 409', function (): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $primero = $e->reportar($id, 'E2E-101', clave: 'reintento-0001');
    foreach (['E2E-102', 'E2E-103', 'E2E-104', 'E2E-105', 'E2E-106'] as $esc) {
        $e->reportar($id, $esc);
    }
    $e->uc['cerrar']->ejecutar('pipeline-ci', 'hosp-norte', $id);
    $repetido = $e->reportar($id, 'E2E-101', clave: 'reintento-0001');            // la respuesta original se perdió; el pipeline reintenta tarde
    igual([Salida::CREADO, Salida::OK, 'RESULTADO_YA_REPORTADO'], [$primero->tipo, $repetido->tipo, $repetido->codigo], 'repetición');
    igual([Salida::CONFLICTO, 'CLAVE_REUTILIZADA'], [($otro = $e->reportar($id, 'E2E-102', 'FALLA', clave: 'reintento-0001'))->tipo, $otro->codigo], 'otro contenido');
    igual(6, count($e->ejecuciones->real->porId('hosp-norte', $id)->resultados()), 'un resultado por escenario');
});

prueba('APP-04', 'Fail-closed (R-08): con el almacén caído → 503 EVIDENCIA_NO_DISPONIBLE y no queda resultado, evidencia ni traza', function (): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $antes = count($e->auditoriaReal()->acciones());
    $e->almacen->caido = true;
    $r = $e->reportar($id, 'E2E-104', 'FALLA');
    igual([Salida::NO_DISPONIBLE, 'EVIDENCIA_NO_DISPONIBLE'], [$r->tipo, $r->codigo], 'salida');
    igual([0, $antes, []], [count($e->ejecuciones->real->porId('hosp-norte', $id)->resultados()), count($e->auditoriaReal()->acciones()), $e->almacen->contenido], 'nada escrito');
});

prueba('APP-05', 'Las reglas se revisan ANTES de tocar el almacén: un resultado rechazado no deja artefactos huérfanos', function (): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $r = $e->reportar($id, 'E2E-999', 'FALLA');
    igual([Salida::CONFLICTO, 'ESCENARIO_FUERA_DEL_PLAN', []], [$r->tipo, $r->codigo, $e->almacen->contenido], 'fuera del plan, almacén vacío');
    // Escenario DEL plan, pero ya reportado: lo rechaza agregar(), no critico(). Su artefacto nuevo tampoco puede quedar.
    $e->reportar($id, 'E2E-104', 'FALLA');
    $antes = array_keys($e->almacen->contenido);
    $dup = $e->reportar($id, 'E2E-104', 'FALLA', 1, [['tipo' => 'captura', 'bytes' => 'captura distinta del segundo reporte']]);
    igual([Salida::CONFLICTO, 'RESULTADO_YA_REPORTADO', $antes], [$dup->tipo, $dup->codigo, array_keys($e->almacen->contenido)], 'duplicado sin huérfanos');
});

prueba('APP-06', 'R-13: sin permiso → 403 (el pipeline no evalúa el gate ni planifica); otro tenant no ve la versión (404)', function (): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $e->completar($id);
    igual([Salida::DENEGADO, Salida::DENEGADO, Salida::DENEGADO], [$e->uc['gate']->ejecutar('pipeline-ci', 'hosp-norte', '2.4.0', $id)->tipo,
        $e->uc['plan']->ejecutar('pipeline-ci', 'hosp-norte', '2.4.0', [])->tipo, $e->uc['reportar']->ejecutar('resp-release', 'hosp-norte', $id, 'clave-00000001', [])->tipo], '403');
    igual(Salida::NO_ENCONTRADO, $e->uc['trazabilidad']->ejecutar('pipeline-ci', 'hosp-sur', '2.4.0')->tipo, 'otro tenant');
});

prueba('APP-07', 'Error de persistencia al leer: el caso de uso devuelve 503 ERROR_PERSISTENCIA, nunca una excepción', function (): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $e->ejecuciones->caidaAlLeer = true;
    igual([Salida::NO_DISPONIBLE, 'ERROR_PERSISTENCIA'], [($r = $e->reportar($id, 'E2E-101'))->tipo, $r->codigo], 'reportar');
    igual('ERROR_PERSISTENCIA', $e->uc['gate']->ejecutar('resp-release', 'hosp-norte', '2.4.0', $id)->codigo, 'gate');
    igual('ERROR_PERSISTENCIA', $e->uc['trazabilidad']->ejecutar('dev-ana', 'hosp-norte', '2.4.0')->codigo, 'trazabilidad');
});

prueba('APP-08', 'Error de persistencia al escribir la traza: la transacción deshace el resultado (nada a medias) y responde 503', function (): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $e->auditoria->caida = true;
    $r = $e->reportar($id, 'E2E-101');
    igual([Salida::NO_DISPONIBLE, 0], [$r->tipo, count($e->ejecuciones->real->porId('hosp-norte', $id)->resultados())], 'sin resultado');
});

prueba('APP-09', 'R-02 y R-03 por el caso de uso: plan incompleto (422 con los faltantes), iniciar con otro digest (409), digest de otra versión (409)', function (): void {
    $e = new Escenario();
    $e->uc['version']->ejecutar('pipeline-ci', 'hosp-norte', ['numero' => '2.5.0', 'commit' => 'abc1234', 'digest' => Escenario::DIGEST, 'modulos' => ['citas']]);
    $plan = $e->uc['plan']->ejecutar('ing-qa', 'hosp-norte', '2.5.0', ['E2E-101']);
    igual([Salida::INVALIDO, 'PLAN_INCOMPLETO', ['E2E-102', 'E2E-106']], [$plan->tipo, $plan->codigo, $plan->datos['detalle']], 'plan');
    $e->uc['plan']->ejecutar('ing-qa', 'hosp-norte', '2.5.0', ['E2E-101', 'E2E-102', 'E2E-106']);
    igual('DIGEST_DISTINTO', $e->uc['iniciar']->ejecutar('pipeline-ci', 'hosp-norte', '2.5.0', 'sha256:' . str_repeat('0', 64), 'staging')->codigo, 'digest');
    igual('DIGEST_EN_USO', $e->uc['version']->ejecutar('pipeline-ci', 'hosp-norte', ['numero' => '2.5.1', 'commit' => 'abc1235', 'digest' => Escenario::DIGEST,
        'modulos' => ['citas']])->codigo, 'el mismo artefacto no puede ser dos versiones');
});

prueba('APP-10', 'El gate no se evalúa sobre una ejecución abortada (409) y una ejecución no se cierra dos veces (409)', function (): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    igual('EJECUCION_ABORTADA', $e->uc['cerrar']->ejecutar('pipeline-ci', 'hosp-norte', $id, 'el runner se quedó sin memoria')->codigo, 'abortar');
    igual([Salida::CONFLICTO, 'EJECUCION_NO_COMPLETADA'], [($g = $e->uc['gate']->ejecutar('resp-release', 'hosp-norte', '2.4.0', $id))->tipo, $g->codigo], 'gate');
    igual('ESTADO_NO_PERMITE_CERRAR', $e->uc['cerrar']->ejecutar('pipeline-ci', 'hosp-norte', $id)->codigo, 'segundo cierre');
});
