<?php

declare(strict_types=1);

use Auditoria\Pruebas\Dobles\Escenario;

prueba('CORR-07', 'HTTP (semana 6): corregir por la API → 201; el detalle del original avisa; 409 YA_CORREGIDO; todo documentado en el contrato 1.1.0', function () use ($apiContrato, $apiCodigos): void {
    $e = Escenario::crear();
    $post = static fn (array $cuerpo) => $e->kernel->manejar(Escenario::http('POST', '/api/v1/auditoria/movimientos', 'token-svc-pacientes-oriente',
        Escenario::H_ORIENTE, $cuerpo));
    $post(Escenario::mov(['usuario' => 'u-equivocado']));
    $correccion = ['event_id' => 'ev-c', 'usuario' => 'u-correcto', 'corrige_evento' => 'ev-1', 'motivo_correccion' => 'Usuario equivocado en el origen'];
    $r = $post(Escenario::mov($correccion));
    igual([201, 2], [$r->estado, $r->cuerpo['secuencia']], 'corrección');
    $detalle = $e->kernel->manejar(Escenario::http('GET', '/api/v1/auditoria/movimientos/1', 'token-auditor-oriente'))->cuerpo['movimiento'];
    igual([2, 'u-equivocado'], [$detalle['corregido_por'], $detalle['usuario']], 'el original avisa');
    $otra = $post(Escenario::mov(['event_id' => 'ev-c2'] + $correccion));
    igual([409, 'YA_CORREGIDO'], [$otra->estado, $otra->cuerpo['error']], 'segunda corrección');
    $contrato = $apiContrato()['POST /api/v1/auditoria/movimientos']['respuestas'];
    afirmar(in_array('409', $contrato, true) && in_array('YA_CORREGIDO', $apiCodigos(), true), 'YA_CORREGIDO no está documentado');
    $yaml = (string) file_get_contents(dirname(__DIR__, 2) . '/api/openapi.yaml');
    foreach (['version: 1.1.0', 'corrige_evento:', 'motivo_correccion:', 'corregido_por:', 'corrige_a:'] as $esperado) {
        afirmar(str_contains($yaml, $esperado), "el contrato no menciona «{$esperado}»");
    }
});
