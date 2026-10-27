<?php

declare(strict_types=1);

use Prescripciones\Aplicacion\Resultados\Resultado;
use Prescripciones\Dominio\Modelo\Alergia;

const JUSTIFICACION_VALIDA = 'Sin alternativa: cultivo sensible solo a cefalosporinas';

// --- Camino feliz -----------------------------------------------------------------------------------------------
prueba('APP-01', 'Camino feliz: emite, guarda la verificación y la traza en UNA transacción', function (): void {
    $e = new Escenario();
    $r = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')]));
    igual([Resultado::CREADO, 'EMITIDA', 1, 'SIN_HALLAZGOS'], [$r->tipo, $r->codigo, $r->datos['id'], $r->datos['verificacion']], 'resultado');
    $p = $e->repositorio->guardadas[0];
    igual([[], null, false], [$p->alergenosEvaluados, $p->justificacion, $p->confirmaSinAlergias], 'P-005 solo tiene una alergia resuelta');
    igual(['PRESCRIPCION_EMITIDA'], $e->auditoria->acciones(), 'traza');
    igual('prescripcion:1', $e->auditoria->movimientos[0]['recurso'], 'recurso sin datos clínicos');
    igual(1, $e->transaccion->confirmadas, 'transacciones confirmadas');
});

prueba('APP-02', 'Camino feliz con advertencia justificada: guarda la justificación y los alérgenos evaluados', function (): void {
    $e = new Escenario();
    $r = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-002', [Escenario::item('CEFA500')], justificacion: '  ' . JUSTIFICACION_VALIDA . ' '));
    igual([Resultado::CREADO, 'ADVERTENCIA'], [$r->tipo, $r->datos['verificacion']], 'resultado');
    $p = $e->repositorio->guardadas[0];
    igual([JUSTIFICACION_VALIDA, ['Penicilina'], 'CRUZADA'], [$p->justificacion, $p->alergenosEvaluados, $p->verificacion->hallazgos[0]->tipo], 'guardado');
});

prueba('APP-03', 'Consulta: devuelve la prescripción con su verificación; otro hospital = inexistente', function (): void {
    $e = new Escenario();
    $e->casos['emitir']->ejecutar(Escenario::solicitud('P-002', [Escenario::item('CEFA500')], justificacion: JUSTIFICACION_VALIDA));
    $r = $e->casos['consultar']->ejecutar('farm-norte', 'hosp-norte', 1);
    igual([Resultado::OK, 'ADVERTENCIA', JUSTIFICACION_VALIDA], [$r->tipo, $r->datos['verificacion']['resultado'], $r->datos['verificacion']['justificacion']], 'consulta');
    $ajeno = $e->casos['consultar']->ejecutar('dr-sur', 'hosp-sur', 1);
    $inexistente = $e->casos['consultar']->ejecutar('dr-sur', 'hosp-sur', 99);
    igual([$inexistente->tipo, $inexistente->codigo], [$ajeno->tipo, $ajeno->codigo], 'mismo 404');
    igual(Resultado::DENEGADO, $e->casos['consultar']->ejecutar('nadie', 'hosp-norte', 1)->tipo, 'sin permiso');
});

prueba('APP-04', 'Verificación previa: informa sin guardar ni trazar', function (): void {
    $e = new Escenario();
    $adv = $e->casos['previa']->ejecutar('dr-lopez', 'hosp-norte', 'P-002', [Escenario::item('CEFA500')]);
    $conf = $e->casos['previa']->ejecutar('dr-lopez', 'hosp-norte', 'P-003', [Escenario::item('CEFA500')]);
    $caida = (new Escenario(new FuenteAlergiasCaida()))->casos['previa']->ejecutar('dr-lopez', 'hosp-norte', 'P-002', [Escenario::item('CEFA500')]);
    igual(['ADVERTENCIA', 'CONFIRMACION_REQUERIDA', 'VERIFICACION_NO_DISPONIBLE'], [$adv->codigo, $conf->codigo, $caida->codigo], 'códigos');
    igual([0, [], 0], [count($e->repositorio->guardadas), $e->auditoria->acciones(), $e->transaccion->confirmadas], 'sin efectos');
});

// --- Reglas de dominio a través del caso de uso -----------------------------------------------------------------
prueba('APP-05', 'R-04: coincidencia directa bloquea aun con justificación y override; traza el bloqueo', function (): void {
    $e = new Escenario();
    $r = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-001', [Escenario::item('AMOX500')], justificacion: JUSTIFICACION_VALIDA));
    igual([Resultado::RECHAZADO, 'BLOQUEO_ALERGIA'], [$r->tipo, $r->codigo], 'resultado');
    igual([0, ['PRESCRIPCION_BLOQUEADA']], [count($e->repositorio->guardadas), $e->auditoria->acciones()], 'efectos');
    igual('paciente:P-001', $e->auditoria->movimientos[0]['recurso'], 'recurso');
});

prueba('APP-06', 'R-05: advertencia sin justificación, con 19 caracteres o sin override → 409', function (): void {
    $e = new Escenario();
    foreach ([[null, 'dr-lopez'], [str_repeat('x', 19), 'dr-lopez'], [JUSTIFICACION_VALIDA, 'dr-ruiz']] as [$justificacion, $usuario]) {
        $r = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-002', [Escenario::item('CEFA500')], $usuario, justificacion: $justificacion));
        igual([Resultado::CONFLICTO, 'JUSTIFICACION_REQUERIDA'], [$r->tipo, $r->codigo], "$usuario / " . var_export($justificacion, true));
    }
    $ok = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-002', [Escenario::item('CEFA500')], justificacion: str_repeat('x', 20)));
    igual(Resultado::CREADO, $ok->tipo, '20 caracteres');
});

prueba('APP-07', 'R-05: cruzada life_threatening / unknown bloquea aunque se justifique', function (): void {
    $e = new Escenario();
    $grave = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-004', [Escenario::item('IBU400', 400)], justificacion: JUSTIFICACION_VALIDA));
    $desc = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-006', [Escenario::item('CEFA500')], justificacion: JUSTIFICACION_VALIDA));
    igual(['BLOQUEO_ALERGIA_CRUZADA', 'BLOQUEO_ALERGIA_CRUZADA'], [$grave->codigo, $desc->codigo], 'códigos');
});

prueba('APP-08', 'R-06: sin alergias evaluadas exige la confirmación y la guarda', function (): void {
    $e = new Escenario();
    $sin = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-003', [Escenario::item('AMOX500')]));
    $con = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-003', [Escenario::item('AMOX500')], confirma: true));
    igual(['CONFIRMACION_REQUERIDA', 'EMITIDA'], [$sin->codigo, $con->codigo], 'códigos');
    igual(true, $e->repositorio->guardadas[0]->confirmaSinAlergias, 'confirmación guardada');
});

prueba('APP-09', 'R-01/R-02: datos inválidos y dosis excedida se rechazan sin consultar alergias', function (): void {
    $fuente = new FuenteAlergiasEnMemoria(Escenario::alergias());
    $e = new Escenario($fuente);
    $a = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500', 0)]));
    $b = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500', 1000, 4)]));
    igual([['DATOS_INVALIDOS', ['items[0].dosis']], ['DOSIS_EXCEDE_MAXIMO', ['items[0]']]],
        [[$a->codigo, $a->datos['campos']], [$b->codigo, $b->datos['items']]], 'rechazos');
    igual(0, $fuente->consultas, 'consultas al expediente');
});

prueba('APP-10', 'R-03: la emisión vuelve a consultar; una alergia nueva tras la previa bloquea', function (): void {
    $fuente = new FuenteAlergiasEnMemoria(Escenario::alergias());
    $e = new Escenario($fuente);
    $previa = $e->casos['previa']->ejecutar('dr-lopez', 'hosp-norte', 'P-005', [Escenario::item('AMOX500')]);
    $fuente->agregar('P-005', new Alergia('J01CA04', 'Amoxicilina', 'allergy', 'severe', 'active'));
    $r = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('AMOX500')]));
    igual(['SIN_HALLAZGOS', 'BLOQUEO_ALERGIA'], [$previa->codigo, $r->codigo], 'previa y emisión');
});

prueba('APP-11', 'R-08 y R-09: sin permiso → denegado; paciente de otro hospital → no encontrado', function (): void {
    $e = new Escenario();
    $sinPermiso = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')], 'farm-norte'));
    $ajeno = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-900', [Escenario::item('PARA500')]));
    igual([Resultado::DENEGADO, Resultado::NO_ENCONTRADO], [$sinPermiso->tipo, $ajeno->tipo], 'tipos');
    igual(0, count($e->repositorio->guardadas), 'guardadas');
});

prueba('APP-12', 'R-11: reintento igual → la misma; distinto → conflicto; carrera concurrente → la ganadora', function (): void {
    $e = new Escenario();
    $a = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')], clave: 'k'));
    $b = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')], clave: 'k'));
    $c = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('IBU400', 400)], clave: 'k'));
    igual([Resultado::CREADO, Resultado::OK, Resultado::CONFLICTO], [$a->tipo, $b->tipo, $c->tipo], 'tipos');
    igual($a->datos['id'], $b->datos['id'], 'mismo id');

    // Carrera: otra petición guarda la misma clave entre nuestra búsqueda y nuestro INSERT.
    $e2 = new Escenario();
    $s = Escenario::solicitud('P-005', [Escenario::item('PARA500')], clave: 'carrera');
    $e2->repositorio->ganadoraConcurrente = new \Prescripciones\Dominio\Modelo\Prescripcion(null, 'hosp-norte', 'P-005', 'dr-lopez', 'carrera',
        $s->huella(), $s->items, new \Prescripciones\Dominio\Modelo\ResultadoVerificacion('SIN_HALLAZGOS', []), [], null, false, new \DateTimeImmutable());
    $r = $e2->casos['emitir']->ejecutar($s);
    igual([Resultado::OK, 'YA_EMITIDA', 1, 1], [$r->tipo, $r->codigo, $r->datos['id'], $e2->repositorio->total()], 'la carrera se resuelve como reintento');
    igual(1, $e2->transaccion->revertidas, 'nuestra transacción se revirtió');
    igual([], $e2->auditoria->acciones(), 'sin traza duplicada');
});

// --- Errores de persistencia -------------------------------------------------------------------------------------
prueba('APP-13', 'R-07: fuente de alergias caída → no disponible, nada emitido, traza aparte', function (): void {
    $e = new Escenario(new FuenteAlergiasCaida());
    $r = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')], confirma: true));
    igual([Resultado::NO_DISPONIBLE, 'VERIFICACION_NO_DISPONIBLE'], [$r->tipo, $r->codigo], 'resultado');
    igual([0, ['VERIFICACION_NO_DISPONIBLE']], [count($e->repositorio->guardadas), $e->auditoria->acciones()], 'efectos');
});

prueba('APP-14', 'La traza falla dentro de la transacción → nada queda guardado (todo o nada)', function (): void {
    $e = new Escenario();
    $e->auditoria->fallar = true;
    $r = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')]));
    igual([Resultado::NO_DISPONIBLE, 'ERROR_PERSISTENCIA'], [$r->tipo, $r->codigo], 'resultado');
    igual([0, 1], [count($e->repositorio->guardadas), $e->transaccion->revertidas], 'revertida');
});

prueba('APP-15', 'El COMMIT falla → 503 y nada guardado', function (): void {
    $e = new Escenario();
    $e->transaccion->fallarAlConfirmar = true;
    $r = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')]));
    igual([Resultado::NO_DISPONIBLE, 0, []], [$r->tipo, count($e->repositorio->guardadas), $e->auditoria->acciones()], 'efectos');
});

prueba('APP-16', 'La base caída en cualquier paso (búsqueda, paciente, consulta) → no disponible, sin excepción', function (): void {
    $e = new Escenario();
    $e->repositorio->fallar = true;
    igual(Resultado::NO_DISPONIBLE, $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')]))->tipo, 'repositorio');
    igual(Resultado::NO_DISPONIBLE, $e->casos['consultar']->ejecutar('dr-lopez', 'hosp-norte', 1)->tipo, 'consulta');
    $e2 = new Escenario();
    $e2->pacientes->fallar = true;
    igual(Resultado::NO_DISPONIBLE, $e2->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')]))->tipo, 'pacientes');
    igual(Resultado::NO_DISPONIBLE, $e2->casos['previa']->ejecutar('dr-lopez', 'hosp-norte', 'P-005', [Escenario::item('PARA500')])->tipo, 'previa');
});
