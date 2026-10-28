<?php

declare(strict_types=1);

/*
 * Semana 6 · Cambio práctico defendido: «anular una prescripción emitida con motivo» (CU-07, R-10).
 * Se escribieron ANTES de implementar el cambio (evidencia/cambio-1-antes-rojo.txt) y entran por HTTP,
 * para que el «rojo» sea comportamiento y no un error de clase inexistente.
 */

$emitirYAnular = static function (Escenario $e, string $motivo = 'Se registró una alergia a cefalosporinas después de emitir', string $token = 'token-dr-lopez'): array {
    $k = $e->kernel();
    $emitida = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-002', 'items' => [Escenario::item('CEFA500')],
        'justificacion' => 'Sin alternativa: cultivo sensible solo a cefalosporinas'], cabeceras: ['idempotency-key' => 'anu-' . bin2hex(random_bytes(4))]));
    $id = $emitida->cuerpo['id'] ?? 0;
    $anulada = $k->manejar(Escenario::http('POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => $motivo], $token));

    return [$k, $id, $anulada];
};

prueba('ANU-01', 'Anular una prescripción emitida → 200 ANULADA; la consulta muestra la anulación y la prescripción original intacta', function () use ($emitirYAnular): void {
    $e = new Escenario();
    [$k, $id] = [$e->kernel(), null];
    [$k, $id, $r] = $emitirYAnular($e);
    $antes = $e->repositorio->todas()[0];
    igual([200, 'ANULADA', $id], [$r->estado, $r->cuerpo['codigo'] ?? null, $r->cuerpo['id'] ?? null], 'respuesta');
    $c = $k->manejar(Escenario::http('GET', "/api/v1/prescripciones/$id", token: 'token-farmacia-norte'));
    igual(['ANULADA', 'Se registró una alergia a cefalosporinas después de emitir', 'dr-lopez'],
        [$c->cuerpo['estado'], $c->cuerpo['anulacion']['motivo'] ?? null, $c->cuerpo['anulacion']['anulada_por'] ?? null], 'consulta');
    igual([$antes->huella, $antes->items, 'ADVERTENCIA'], [$e->repositorio->todas()[0]->huella, $e->repositorio->todas()[0]->items,
        $c->cuerpo['verificacion']['resultado']], 'lo emitido no cambió');
});

prueba('ANU-02', 'Motivo de menos de 10 caracteres (sin contar espacios) → 422 MOTIVO_REQUERIDO y nada anulado', function () use ($emitirYAnular): void {
    $e = new Escenario();
    [$k, $id, $r] = $emitirYAnular($e, '   corto   ');
    igual([422, 'MOTIVO_REQUERIDO'], [$r->estado, $r->cuerpo['error'] ?? null], 'respuesta');
    igual('EMITIDA', $k->manejar(Escenario::http('GET', "/api/v1/prescripciones/$id"))->cuerpo['estado'] ?? null, 'sigue emitida');
});

prueba('ANU-03', 'Una prescripción ya anulada no se vuelve a anular → 409 ESTADO_NO_PERMITE_ANULAR', function () use ($emitirYAnular): void {
    $e = new Escenario();
    [$k, $id] = $emitirYAnular($e);
    $otra = $k->manejar(Escenario::http('POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => 'Segundo intento de anulación']));
    igual([409, 'ESTADO_NO_PERMITE_ANULAR'], [$otra->estado, $otra->cuerpo['error'] ?? null], 'respuesta');
    igual(['PRESCRIPCION_EMITIDA', 'PRESCRIPCION_ANULADA'], $e->auditoria->acciones(), 'una sola traza de anulación');
});

prueba('ANU-04', 'Tenant y permisos: otro hospital o inexistente → el mismo 404; sin prescripcion.anular (farmacia) → 403', function () use ($emitirYAnular): void {
    $e = new Escenario();
    [$k, $id] = $emitirYAnular($e, token: 'token-farmacia-norte');
    $ajeno = $k->manejar(Escenario::http('POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => 'Motivo suficientemente largo'], 'token-dr-sur', 'hosp-sur'));
    $inexistente = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones/999/anulacion', ['motivo' => 'Motivo suficientemente largo'], 'token-dr-sur', 'hosp-sur'));
    $farmacia = $k->manejar(Escenario::http('POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => 'Motivo suficientemente largo'], 'token-farmacia-norte'));
    igual([404, 'NO_ENCONTRADO'], [$ajeno->estado, $ajeno->cuerpo['error'] ?? null], 'otro hospital');
    igual([$inexistente->estado, $inexistente->cuerpo], [$ajeno->estado, $ajeno->cuerpo], 'indistinguible de inexistente');
    igual([403, 'PERMISO_DENEGADO'], [$farmacia->estado, $farmacia->cuerpo['error'] ?? null], 'farmacia');
    igual('EMITIDA', $k->manejar(Escenario::http('GET', "/api/v1/prescripciones/$id"))->cuerpo['estado'] ?? null, 'sigue emitida');
});

prueba('ANU-05', 'Todo o nada: la anulación y su traza PRESCRIPCION_ANULADA van juntas; si la traza falla, no queda anulada', function () use ($emitirYAnular): void {
    $e = new Escenario();
    [$k, $id, $r] = $emitirYAnular($e);
    igual(['PRESCRIPCION_EMITIDA', 'PRESCRIPCION_ANULADA'], $e->auditoria->acciones(), 'trazas');
    igual("prescripcion:$id", $e->auditoria->movimientos()[1]['recurso'] ?? null, 'recurso sin datos clínicos');
    $e2 = new Escenario();
    $k2 = $e2->kernel();
    $id2 = $k2->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [Escenario::item('PARA500')]],
        cabeceras: ['idempotency-key' => 'anu-5']))->cuerpo['id'];
    $e2->auditoria->fallar = true;
    $r2 = $k2->manejar(Escenario::http('POST', "/api/v1/prescripciones/$id2/anulacion", ['motivo' => 'Motivo suficientemente largo']));
    $e2->auditoria->fallar = false;
    igual([503, 'EMITIDA'], [$r2->estado, $k2->manejar(Escenario::http('GET', "/api/v1/prescripciones/$id2"))->cuerpo['estado'] ?? null], 'nada a medias');
});

prueba('ANU-06', 'Bloqueo seguro asimétrico: con el Expediente caído no se emite (503), pero SÍ se puede anular', function (): void {
    $fuente = new FuenteConmutable(Escenario::fuenteContada());
    $e = new Escenario($fuente);
    $k = $e->kernel();
    $id = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [Escenario::item('PARA500')]],
        cabeceras: ['idempotency-key' => 'anu-6']))->cuerpo['id'];
    $fuente->caida = true;
    $emitir = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [Escenario::item('IBU400', 400)]],
        cabeceras: ['idempotency-key' => 'anu-6b']));
    $anular = $k->manejar(Escenario::http('POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => 'Paciente refiere reacción al paracetamol']));
    igual([503, 200], [$emitir->estado, $anular->estado], 'emitir falla cerrado; anular no depende del Expediente');
});

prueba('ANU-07', 'Entrada mal formada: sin «motivo» o no texto → 400 CUERPO_INVALIDO; id no numérico → 404', function (): void {
    $k = (new Escenario())->kernel();
    $sinMotivo = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones/1/anulacion', ['razon' => 'x']));
    $numero = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones/1/anulacion', ['motivo' => 12345678901]));
    igual([[400, 'CUERPO_INVALIDO'], [400, 'CUERPO_INVALIDO']], [[$sinMotivo->estado, $sinMotivo->cuerpo['error'] ?? null], [$numero->estado, $numero->cuerpo['error'] ?? null]], 'respuestas');
    igual(404, $k->manejar(Escenario::http('POST', '/api/v1/prescripciones/abc/anulacion', ['motivo' => 'Motivo suficientemente largo']))->estado, 'id no numérico');
});
