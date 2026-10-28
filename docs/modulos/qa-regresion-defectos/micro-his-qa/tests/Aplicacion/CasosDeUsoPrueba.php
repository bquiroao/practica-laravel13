<?php

declare(strict_types=1);

use Qa\Aplicacion\Resultados\Salida;
use Qa\Dominio\Modelo\EstadoDefecto;
use Qa\Dominio\Modelo\Severidad;

$casoNuevo = ['id' => 'TC-200', 'titulo' => 'H4 diagnóstico: código CIE-10 válido', 'precondiciones' => 'Episodio abierto',
    'pasos' => [['accion' => 'Registrar diagnóstico con código X99.9', 'esperado' => 'Se rechaza']], 'prioridad' => 'ALTA', 'requisitos' => ['RF-07']];

// --- Camino feliz -----------------------------------------------------------------------------------------------
prueba('APP-01', 'Camino feliz completo: diseñar → aprobar → planificar → iniciar → FALLA con defecto → clasificar → corregir → re-ejecutar PASA → GO', function () use ($casoNuevo): void {
    $e = new Escenario();
    igual('CASO_DISENADO', $e->uc['disenar']->ejecutar('ana-qa', 'hosp-norte', $casoNuevo)->codigo, 'diseño');
    igual('CASO_APROBADO', $e->uc['aprobar']->ejecutar('lider-qa', 'hosp-norte', 'TC-200')->codigo, 'aprobación');
    $id = $e->campanaEnCurso(['TC-107', 'TC-108', 'TC-200']);
    $falla = $e->registrar($id, 'TC-107', 'FALLA');
    igual([Salida::CREADO, 1, 1], [$falla->tipo, $falla->datos['numero'], $falla->datos['defecto']], 'falla con defecto');
    $e->registrar($id, 'TC-108', 'PASA');
    $e->registrar($id, 'TC-200', 'PASA');
    igual('DEFECTO_CLASIFICADO', $e->uc['clasificar']->ejecutar('lider-qa', 'hosp-norte', 1, ['severidad' => 'CRITICA', 'prioridad' => 'ALTA',
        'motivo' => 'Permite editar una nota clínica firmada'])->codigo, 'clasificación');
    $noGo = $e->defectos->criticosAbiertos('hosp-norte', ['TC-107']);
    igual(1, $noGo, 'un crítico abierto bloquearía el GO');
    igual('DEFECTO_CORREGIDO', $e->uc['corregir']->ejecutar('dev-luis', 'hosp-norte', 1, '1.4.1+def5678')->codigo, 'corrección');
    $reejecucion = $e->registrar($id, 'TC-107', 'PASA', ['verifica_defecto' => 1]);
    igual([2, ['id' => 1, 'estado' => 'CERRADO']], [$reejecucion->datos['numero'], $reejecucion->datos['defecto_verificado']], 're-ejecución cierra');
    $cierre = $e->uc['cerrar']->ejecutar('lider-qa', 'hosp-norte', $id);
    igual(['GO', 1.0, []], [$cierre->datos['decision'], $cierre->datos['tasa_aprobados'], $cierre->datos['causas']], 'decisión');
    igual(['CASO_DISENADO', 'CASO_APROBADO', 'CAMPANA_PLANIFICADA', 'CAMPANA_INICIADA', 'RESULTADO_REGISTRADO', 'RESULTADO_REGISTRADO', 'RESULTADO_REGISTRADO',
        'DEFECTO_CLASIFICADO', 'DEFECTO_CORREGIDO', 'RESULTADO_REGISTRADO', 'CAMPANA_CERRADA_GO'], $e->auditoria->acciones(), 'traza de cada paso');
});

prueba('APP-02', 'Trazabilidad: requisito → caso → último intento → evidencia (solo huella) → defecto', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    $e->registrar($id, 'TC-107', 'FALLA');
    $e->registrar($id, 'TC-108', 'PASA');
    $t = $e->uc['trazabilidad']->ejecutar('producto', 'hosp-norte', $id);
    $fila = $t->datos['casos'][0];
    igual(['TC-107', 'FALLA', 1, 'MAYOR', false], [$fila['caso'], $fila['ultimo_resultado'], count($fila['evidencias']), $fila['defectos'][0]['severidad'],
        $fila['defectos'][0]['clasificado']], 'fila TC-107');
    igual(hash('sha256', Escenario::PNG . 'TC-107'), $fila['evidencias'][0]['huella'], 'huella');
    afirmar(!str_contains(json_encode($t->datos), 'captura-de-prueba'), 'el contenido de la evidencia no sale en la trazabilidad');
    igual('PENDIENTE', $t->datos['casos'][2]['ultimo_resultado'], 'TC-109 sin ejecutar');
    igual(['RF-07' => ['TC-107', 'TC-108', 'TC-109'], 'RF-08' => ['TC-108']], $t->datos['requisitos'], 'requisito → casos');
});

prueba('APP-03', 'Idempotencia (R-12): la misma clave y el mismo contenido devuelve el MISMO intento; con otro contenido, 409', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    $primero = $e->registrar($id, 'TC-107', 'PASA', [], 'tito-tester', 'reintento-0001');
    $repetido = $e->registrar($id, 'TC-107', 'PASA', [], 'tito-tester', 'reintento-0001');
    igual([Salida::OK, 'RESULTADO_YA_REGISTRADO', $primero->datos['intento']], [$repetido->tipo, $repetido->codigo, $repetido->datos['intento']], 'repetición');
    igual('CLAVE_REUTILIZADA', $e->registrar($id, 'TC-108', 'PASA', [], 'tito-tester', 'reintento-0001')->codigo, 'otro contenido');
    igual(1, count($e->campanas->intentos), 'un solo intento guardado');
});

prueba('APP-15', 'Idempotencia ANTES que el estado: un reintento que llega después de cerrar la campaña o el defecto devuelve el mismo intento, no 409', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso(['TC-107', 'TC-108']);
    $corregido = $e->defectos->sembrar('hosp-norte', $id, 'TC-107', Severidad::MAYOR, EstadoDefecto::CORREGIDO);
    $original = $e->registrar($id, 'TC-107', 'PASA', ['verifica_defecto' => $corregido->id], 'tito-tester', 'reintento-red-01');
    $e->registrar($id, 'TC-108', 'PASA');
    $e->uc['cerrar']->ejecutar('lider-qa', 'hosp-norte', $id);
    // La respuesta del primer envío se perdió en la red; el cliente reintenta con la misma clave.
    $reintento = $e->registrar($id, 'TC-107', 'PASA', ['verifica_defecto' => $corregido->id], 'tito-tester', 'reintento-red-01');
    igual([Salida::OK, 'RESULTADO_YA_REGISTRADO', $original->datos['intento']], [$reintento->tipo, $reintento->codigo, $reintento->datos['intento']], 'mismo intento');
});

// --- Reglas de dominio a través del caso de uso -----------------------------------------------------------------
prueba('APP-04', 'R-05: FALLA o BLOQUEADO sin evidencia → 422; una referencia externa NO cuenta como evidencia', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    $r = $e->registrar($id, 'TC-107', 'BLOQUEADO', ['evidencias' => [], 'referencias' => [['url' => 'https://ci.hospital.gt/build/88', 'descripcion' => 'log']]]);
    igual([Salida::INVALIDO, 'EVIDENCIA_REQUERIDA'], [$r->tipo, $r->codigo], 'bloqueado sin evidencia');
    igual([0, []], [count($e->campanas->intentos), $e->almacen->archivos], 'nada escrito');
});

prueba('APP-05', 'R-06: FALLA sin defecto → 422; R-10: NO_APLICA sin motivo → 422', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    igual('DEFECTO_REQUERIDO', $e->registrar($id, 'TC-107', 'FALLA', ['defecto' => null])->codigo, 'falla sin defecto');
    igual('MOTIVO_REQUERIDO', $e->registrar($id, 'TC-107', 'NO_APLICA')->codigo, 'no aplica sin motivo');
    igual(Salida::CREADO, $e->registrar($id, 'TC-107', 'NO_APLICA', ['motivo' => 'El build no toca notas'])->tipo, 'con motivo');
});

prueba('APP-06', 'R-03 en el caso de uso: no inicia sin los críticos de la matriz y devuelve cuáles faltan', function (): void {
    $e = new Escenario();
    $p = $e->uc['planificar']->ejecutar('lider-qa', 'hosp-norte', ['nombre' => 'Incompleta', 'funcionalidades' => ['expediente', 'agenda-camas'], 'casos' => ['TC-109']]);
    $r = $e->uc['iniciar']->ejecutar('lider-qa', 'hosp-norte', $p->datos['id'], '1.4.0');
    igual([Salida::INVALIDO, 'ALCANCE_INCOMPLETO', ['TC-106', 'TC-107', 'TC-108']], [$r->tipo, $r->codigo, $r->datos['detalle']], 'faltan críticos');
});

prueba('APP-07', 'R-02 y R-04 en el caso de uso: autor no aprueba (409); campaña planificada no admite resultados (409)', function () use ($casoNuevo): void {
    $e = new Escenario();
    $e->uc['disenar']->ejecutar('lider-qa', 'hosp-norte', $casoNuevo);
    igual([Salida::CONFLICTO, 'APROBACION_POR_AUTOR'], (static function () use ($e): array {
        $r = $e->uc['aprobar']->ejecutar('lider-qa', 'hosp-norte', 'TC-200');

        return [$r->tipo, $r->codigo];
    })(), 'autor');
    $p = $e->uc['planificar']->ejecutar('lider-qa', 'hosp-norte', ['nombre' => 'X', 'funcionalidades' => ['expediente'], 'casos' => ['TC-107', 'TC-108']]);
    igual('CAMPANA_NO_ADMITE_RESULTADOS', $e->registrar($p->datos['id'], 'TC-107', 'PASA')->codigo, 'planificada');
});

prueba('APP-08', 'R-08: re-ejecutar PASA sobre un defecto que no está CORREGIDO → 409; FALLA en la re-ejecución lo REABRE sin pedir otro defecto', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    $abierto = $e->defectos->sembrar('hosp-norte', $id, 'TC-107', Severidad::MAYOR, EstadoDefecto::ABIERTO);
    igual('ESTADO_NO_PERMITE_VERIFICAR', $e->registrar($id, 'TC-107', 'PASA', ['verifica_defecto' => $abierto->id])->codigo, 'no corregido');
    $corregido = $e->defectos->sembrar('hosp-norte', $id, 'TC-107', Severidad::MAYOR, EstadoDefecto::CORREGIDO);
    $r = $e->registrar($id, 'TC-107', 'FALLA', ['verifica_defecto' => $corregido->id, 'defecto' => null]);
    igual([Salida::CREADO, 'REABIERTO', null], [$r->tipo, $r->datos['defecto_verificado']['estado'], $r->datos['defecto']], 'reabierto');
});

prueba('APP-09', 'Permisos y tenant: sin permiso → 403; el tester de otro hospital ve la campaña como inexistente (404)', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    igual(Salida::DENEGADO, $e->registrar($id, 'TC-107', 'PASA', [], 'dev-luis')->tipo, 'dev no ejecuta');
    igual(Salida::DENEGADO, $e->uc['cerrar']->ejecutar('tito-tester', 'hosp-norte', $id)->tipo, 'tester no cierra');
    igual(Salida::DENEGADO, $e->uc['corregir']->ejecutar('tito-tester', 'hosp-norte', 1, '1.0.0')->tipo, 'tester no corrige');
    $sur = $e->uc['registrar']->ejecutar('tester-sur', 'hosp-sur', $id, Escenario::clave(), ['caso' => 'TC-107', 'resultado' => 'PASA']);
    igual(Salida::NO_ENCONTRADO, $sur->tipo, 'otro tenant');
    $planSur = $e->uc['planificar']->ejecutar('lider-qa', 'hosp-norte', ['nombre' => 'X', 'funcionalidades' => ['expediente'], 'casos' => ['TC-999']]);
    igual(['CASO_INEXISTENTE', ['TC-999']], [$planSur->codigo, $planSur->datos['detalle']], 'caso inexistente');
});

prueba('APP-10', 'Evidencia > 5 MB → 413 sin escribir nada; formato inválido → 422', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    $grande = $e->registrar($id, 'TC-107', 'FALLA', ['evidencias' => [['tipo' => 'registro', 'bytes' => str_repeat('a', 5 * 1024 * 1024 + 1)]]]);
    igual([Salida::DEMASIADO_GRANDE, 0], [$grande->tipo, count($e->almacen->archivos)], '413');
    igual('TIPO_NO_PERMITIDO', $e->registrar($id, 'TC-107', 'FALLA', ['evidencias' => [['tipo' => 'captura', 'bytes' => 'GIF89a']]])->codigo, 'formato');
});

// --- Errores de persistencia (dobles) -----------------------------------------------------------------------
prueba('APP-11', 'Almacén de evidencias caído (fail-closed): 503 y NO queda intento, defecto ni traza', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    $antes = count($e->auditoria->movimientos);
    $e->almacen->caido = true;
    $r = $e->registrar($id, 'TC-107', 'FALLA');
    igual([Salida::NO_DISPONIBLE, 'EVIDENCIA_NO_DISPONIBLE'], [$r->tipo, $r->codigo], 'resultado');
    igual([0, 0, $antes], [count($e->campanas->intentos), count($e->defectos->deCampana('hosp-norte', $id)), count($e->auditoria->movimientos)], 'sin escrituras');
});

prueba('APP-12', 'La traza falla dentro de la transacción: 503 y se deshacen el intento y el defecto (nada a medias)', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    $e->auditoria->fallarEn = 'registrar';
    $r = $e->registrar($id, 'TC-107', 'FALLA');
    igual([Salida::NO_DISPONIBLE, 'ERROR_PERSISTENCIA'], [$r->tipo, $r->codigo], 'resultado');
    igual([0, 0, 1], [count($e->campanas->intentos), count($e->defectos->deCampana('hosp-norte', $id)), $e->transaccion->revertidas], 'revertido');
});

prueba('APP-13', 'La base no responde al leer: cada caso de uso devuelve 503 ERROR_PERSISTENCIA, nunca una excepción', function () use ($casoNuevo): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    $e->casos->fallarEn = 'porId';
    $e->campanas->fallarEn = 'porId';
    $e->defectos->fallarEn = 'porId';
    $salidas = [
        $e->uc['disenar']->ejecutar('ana-qa', 'hosp-norte', $casoNuevo),
        $e->uc['aprobar']->ejecutar('lider-qa', 'hosp-norte', 'TC-111'),
        $e->uc['iniciar']->ejecutar('lider-qa', 'hosp-norte', $id, '1.4.0'),
        $e->registrar($id, 'TC-107', 'PASA'),
        $e->uc['clasificar']->ejecutar('lider-qa', 'hosp-norte', 1, ['severidad' => 'MAYOR', 'prioridad' => 'ALTA']),
        $e->uc['corregir']->ejecutar('dev-luis', 'hosp-norte', 1, '1.4.1'),
        $e->uc['cerrar']->ejecutar('lider-qa', 'hosp-norte', $id),
        $e->uc['trazabilidad']->ejecutar('producto', 'hosp-norte', $id),
    ];
    igual(array_fill(0, 8, 'ERROR_PERSISTENCIA'), array_map(static fn (Salida $s): string => $s->codigo, $salidas), 'códigos');
});

prueba('APP-14', 'Doble cierre concurrente: el segundo choca con el estado guardado (409), no deja dos decisiones', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso(['TC-107', 'TC-108']);
    $e->registrar($id, 'TC-107', 'PASA');
    $e->registrar($id, 'TC-108', 'PASA');
    // Dos peticiones leen la campaña EN_CURSO antes de que cualquiera escriba: se simula cerrando una copia vieja.
    $vieja = $e->campanas->porId('hosp-norte', $id);
    igual('CAMPANA_CERRADA', $e->uc['cerrar']->ejecutar('lider-qa', 'hosp-norte', $id)->codigo, 'primer cierre');
    $vieja->cerrar(0);
    igual('ESTADO_CAMBIO_CONCURRENTE', lanza(\Qa\Dominio\Excepciones\ReglaRechazada::class, static fn () => $e->campanas->actualizarEstado($vieja), 'segundo')->codigo, 'segundo cierre');
});
