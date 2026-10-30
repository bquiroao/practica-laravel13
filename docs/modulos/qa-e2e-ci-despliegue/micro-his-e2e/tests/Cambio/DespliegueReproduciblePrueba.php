<?php

declare(strict_types=1);

/*
 * Semana 6 — Cambio práctico: despliegue reproducible (CU-11, R-11). Escritas ANTES de implementar.
 * «No se instala un digest sin un APROBADO vigente sobre ESE digest; la guía es versionada; si la verificación posterior
 *  falla, el despliegue queda REVERTIDO a lo que estaba».
 */

use E2e\Aplicacion\Resultados\Salida;
use E2e\Presentacion\Http\Solicitud;

/** Versión 2.4.0 aprobada por el gate en la hora fija del escenario; devuelve el escenario listo. */
$aprobada = static function (string $numero = '2.4.0', string $digest = Escenario::DIGEST, ?Escenario $e = null): Escenario {
    $e ??= new Escenario();
    $id = $e->ejecucionEnCurso(numero: $numero, digest: $digest);
    $e->completar($id);
    $g = $e->uc['gate']->ejecutar('resp-release', 'hosp-norte', $numero, $id);
    if (($g->datos['decision'] ?? '') !== 'APROBADO') {
        throw new RuntimeException('la versión de prueba no quedó aprobada');
    }

    return $e;
};
$desplegar = static fn (Escenario $e, array $cambios = [], string $numero = '2.4.0', string $usuario = 'ops-luis'): Salida
    => $e->uc['desplegar']->ejecutar($usuario, 'hosp-norte', $numero, $cambios + ['digest' => Escenario::DIGEST, 'entorno' => 'produccion', 'guia' => 'guia-2026.1', 'smoke' => 'PASA']);

prueba('CAM-01', 'R-11: con el último gate APROBADO, vigente y el MISMO digest, el despliegue queda DESPLEGADO con la guía y el operador', function () use ($aprobada, $desplegar): void {
    $e = $aprobada();
    $d = $desplegar($e);
    igual([Salida::CREADO, 'DESPLIEGUE_REGISTRADO', 'DESPLEGADO', 'guia-2026.1', null], [$d->tipo, $d->codigo, $d->datos['estado'], $d->datos['guia'], $d->datos['revertido_a']], 'despliegue');
});

prueba('CAM-02', 'R-11: sin evaluación, o si la ÚLTIMA evaluación es RECHAZADO (aunque antes hubo un APROBADO), no se despliega: 409 GATE_NO_APROBADO', function () use ($aprobada, $desplegar): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $e->completar($id);
    igual([Salida::CONFLICTO, 'GATE_NO_APROBADO'], [($d = $desplegar($e))->tipo, $d->codigo], 'sin evaluación');
    $e = $aprobada();
    $e->reloj->avanzar('+25 hours');                                                 // la misma ejecución, reevaluada tarde: RECHAZADO por antigüedad
    $e->uc['gate']->ejecutar('resp-release', 'hosp-norte', '2.4.0', 1);
    igual('GATE_NO_APROBADO', $desplegar($e)->codigo, 'el último es RECHAZADO');
});

prueba('CAM-03', 'R-03/R-11: el digest instalado tiene que ser el evaluado: otro digest → 409 DIGEST_DISTINTO', function () use ($aprobada, $desplegar): void {
    igual('DIGEST_DISTINTO', $desplegar($aprobada(), ['digest' => 'sha256:' . str_repeat('9', 64)])->codigo, 'digest');
});

prueba('CAM-04', 'R-11: la aprobación vence a las 72 h: 72 h exactas despliega, 72 h y 1 s → 409 APROBACION_VENCIDA', function () use ($aprobada, $desplegar): void {
    $e = $aprobada();
    $e->reloj->avanzar('+72 hours +1 second');
    igual('APROBACION_VENCIDA', $desplegar($e)->codigo, 'vencida');
    $e = $aprobada();
    $e->reloj->avanzar('+72 hours');
    igual('DESPLIEGUE_REGISTRADO', $desplegar($e)->codigo, 'límite');
});

prueba('CAM-05', 'R-11: si la verificación posterior (smoke) falla, queda REVERTIDO a la versión DESPLEGADA antes en ese entorno; la guía no versionada → 422', function () use ($aprobada, $desplegar): void {
    $e = $aprobada();
    $desplegar($e);                                                                   // 2.4.0 desplegada en producción
    $aprobada('2.5.0', 'sha256:' . str_repeat('5', 64), $e);
    $d = $desplegar($e, ['digest' => 'sha256:' . str_repeat('5', 64), 'smoke' => 'FALLA'], '2.5.0');
    igual(['REVERTIDO', '2.4.0'], [$d->datos['estado'], $d->datos['revertido_a']], 'revertido a la anterior');
    igual(['DATOS_INVALIDOS', ['guia']], [($x = $desplegar($e, ['guia' => 'la de siempre']))->codigo, $x->datos['detalle']], 'guía');
});

prueba('CAM-06', 'R-13 por HTTP: el operador despliega (201) y el pipeline no puede (403); la trazabilidad muestra el despliegue', function () use ($aprobada): void {
    $e = $aprobada();
    $k = $e->kernel();
    $pedir = static fn (string $token): int => $k->manejar(new Solicitud('POST', '/api/v1/e2e/versiones/2.4.0/despliegues', ['authorization' => "Bearer $token",
        'x-tenant-id' => 'hosp-norte'], json_encode(['digest' => Escenario::DIGEST, 'entorno' => 'produccion', 'guia' => 'guia-2026.1', 'smoke' => 'PASA'])))->estado;
    igual([403, 201], [$pedir('token-pipeline'), $pedir('token-ops')], 'estados');
    $t = $e->uc['trazabilidad']->ejecutar('dev-ana', 'hosp-norte', '2.4.0');
    igual([['entorno' => 'produccion', 'digest' => Escenario::DIGEST, 'estado' => 'DESPLEGADO', 'guia' => 'guia-2026.1', 'operador' => 'ops-luis', 'revertido_a' => null]],
        array_map(static fn (array $d): array => array_diff_key($d, ['id' => 1, 'en' => 1]), $t->datos['despliegues']), 'trazabilidad');
});
