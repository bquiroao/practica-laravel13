<?php

declare(strict_types=1);

/*
 * Semana 6 — Cambio práctico «corrección trazable de un movimiento». Escritas ANTES de implementar
 * (rojo → verde). Un emisor que registró mal un movimiento (p. ej. otro usuario) lo corrige con OTRO
 * movimiento que lo referencia; el original no cambia nunca (R-02).
 */

use Auditoria\Aplicacion\Comandos\ComandoDetalle;
use Auditoria\Aplicacion\Comandos\ComandoVerificar;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Pruebas\Dobles\Escenario;

const CORR_MOTIVO = 'El integrador atribuyó la consulta al usuario equivocado';

/** Corrección del evento $original (del mismo emisor) con los cambios indicados. */
$corr = static fn (string $eventId, string $original, array $cambios = [], ?string $motivo = CORR_MOTIVO): array =>
    Escenario::mov(array_merge(['event_id' => $eventId, 'usuario' => 'u-correcto', 'ocurrido_en' => '2026-10-21T11:00:00-06:00',
        'corrige_evento' => $original], $motivo === null ? [] : ['motivo_correccion' => $motivo], $cambios));

$corrPar = static fn (Resultado $r): array => [$r->estado, $r->codigo];

prueba('CORR-01', 'Corregir un movimiento propio: el original queda IDÉNTICO; la corrección es otro movimiento sellado en la misma cadena que lo referencia', function () use ($corr): void {
    $e = Escenario::crear();
    $original = Escenario::registrar($e, Escenario::mov(['usuario' => 'u-equivocado']));
    $antes = $e->bitacora->porId($original->datos['id'])->completo();
    $r = Escenario::registrar($e, $corr('ev-c1', 'ev-1'));
    igual([Resultado::CREADO, 2], [$r->estado, $r->datos['secuencia']], 'corrección registrada a continuación');
    igual($antes, $e->bitacora->porId($original->datos['id'])->completo(), 'el original no cambió (ni su sello)');
    $correccion = $e->bitacora->porId($r->datos['id'])->completo();
    igual([1, CORR_MOTIVO, 'u-correcto'], [$correccion['corrige_a'], $correccion['motivo_correccion'], $correccion['usuario']], 'la corrección');
    igual('INTEGRA', $e->casos['verificar']->ejecutar(new ComandoVerificar('superadmin', Escenario::H_ORIENTE))->datos['estado'], 'cadena íntegra');
});

prueba('CORR-02', 'El motivo es obligatorio (≥ 10 caracteres, sin contar espacios); un motivo sin corrección también se rechaza', function () use ($corr, $corrPar): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::mov());
    foreach ([null, '   ', 'corto     '] as $motivo) {
        $r = Escenario::registrar($e, $corr('ev-c', 'ev-1', [], $motivo));
        igual([Resultado::INVALIDO, 'DATOS_INVALIDOS', ['motivo_correccion']], [$r->estado, $r->codigo, array_keys($r->datos['campos'] ?? [])],
            'motivo ' . json_encode($motivo));
    }
    $r = Escenario::registrar($e, Escenario::mov(['event_id' => 'ev-x', 'motivo_correccion' => CORR_MOTIVO]));
    igual(['motivo_correccion'], array_keys($r->datos['campos'] ?? []), 'motivo sin corrige_evento');
    igual([Resultado::INVALIDO, 'DATOS_INVALIDOS'], $corrPar(Escenario::registrar($e, $corr('ev-y', 'ev-y'))), 'corregirse a sí mismo');
    igual(1, count($e->bitacora->todos()), 'nada se registró');
});

prueba('CORR-03', 'Tenant y emisor: corregir un evento de OTRO hospital o de OTRO emisor da la MISMA respuesta que uno inexistente', function () use ($corr): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::mov(['event_id' => 'ev-norte', 'hospital' => Escenario::H_NORTE]), 'svc-pacientes', Escenario::H_NORTE);
    Escenario::registrar($e, Escenario::mov(['event_id' => 'ev-rbac', 'modulo' => 'rbac', 'accion' => 'ROL_ASIGNADO']), 'svc-rbac');
    $respuestas = [];
    foreach (['ev-norte', 'ev-rbac', 'ev-no-existe'] as $i => $original) {
        $r = Escenario::registrar($e, $corr("ev-c$i", $original));
        $respuestas[$original] = [$r->estado, $r->codigo, $r->datos];
    }
    igual($respuestas['ev-no-existe'], $respuestas['ev-norte'], 'otro hospital = inexistente');
    igual($respuestas['ev-no-existe'], $respuestas['ev-rbac'], 'otro emisor = inexistente');
    igual([Resultado::INVALIDO, 'DATOS_INVALIDOS'], array_slice($respuestas['ev-no-existe'], 0, 2), 'código');
});

prueba('CORR-04', 'Una sola corrección por movimiento (409 YA_CORREGIDO); para volver a corregir se corrige la corrección', function () use ($corr, $corrPar): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::mov());
    Escenario::registrar($e, $corr('ev-c1', 'ev-1'));
    igual([Resultado::CONFLICTO, 'YA_CORREGIDO'], $corrPar(Escenario::registrar($e, $corr('ev-c2', 'ev-1'))), 'segunda corrección del original');
    $r = Escenario::registrar($e, $corr('ev-c3', 'ev-c1', ['usuario' => 'u-definitivo']));
    igual([Resultado::CREADO, 2], [$r->estado, $e->bitacora->porId($r->datos['id'])->completo()['corrige_a']], 'corregir la corrección');
});

prueba('CORR-05', 'Reintentar la MISMA corrección (mismo event_id) es idempotente: 200 YA_REGISTRADO, no 409 YA_CORREGIDO', function () use ($corr, $corrPar): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::mov());
    $primera = Escenario::registrar($e, $corr('ev-c1', 'ev-1'));
    $reintento = Escenario::registrar($e, $corr('ev-c1', 'ev-1'));
    igual([Resultado::OK, 'YA_REGISTRADO', $primera->datos['id']], [$reintento->estado, $reintento->codigo, $reintento->datos['id']], 'reintento');
});

prueba('CORR-06', 'Consulta por fechas: el original sigue en SU fecha, marcado con corregido_por; la corrección aparece en la suya con corrige_a', function () use ($corr): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::mov(['usuario' => 'u-equivocado', 'ocurrido_en' => '2026-10-20T09:00:00-06:00']));
    $c = Escenario::registrar($e, $corr('ev-c1', 'ev-1'));
    $dia20 = Escenario::consultar($e, ['desde' => '2026-10-20', 'hasta' => '2026-10-20'])->datos['movimientos'];
    igual([[1, $c->datos['id'], null]], array_map(static fn (array $m): array => [$m['id'], $m['corregido_por'], $m['corrige_a']], $dia20), 'día del original');
    $dia21 = Escenario::consultar($e, ['desde' => '2026-10-21', 'hasta' => '2026-10-21', 'usuario' => 'u-correcto'])->datos['movimientos'];
    igual([[$c->datos['id'], null, 1]], array_map(static fn (array $m): array => [$m['id'], $m['corregido_por'], $m['corrige_a']], $dia21), 'día de la corrección');
    $detalle = $e->casos['detalle']->ejecutar(new ComandoDetalle('auditor-oriente', Escenario::H_ORIENTE, 1))->datos['movimiento'];
    igual([$c->datos['id'], 'u-equivocado'], [$detalle['corregido_por'], $detalle['usuario']], 'el detalle del original avisa');
});
