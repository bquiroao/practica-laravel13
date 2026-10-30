<?php

declare(strict_types=1);

/*
 * Semana 7 · Escenarios de caracterización de «reportar un resultado» y «registrar un despliegue». Se grabaron ANTES del
 * refactor (bin/caracterizar.php → esperado.json) y CAR-01 exige que después respondan EXACTAMENTE lo mismo: tipo, código y
 * datos de la Salida, y lo que quedó guardado. Incluye combinaciones de errores: el ORDEN de las reglas decide cuál sale.
 */

use E2e\Aplicacion\Resultados\Salida;

$foto = static fn (Salida $s, Escenario $e, int $id): array => ['tipo' => $s->tipo, 'codigo' => $s->codigo, 'datos' => $s->datos,
    'guardados' => count($e->ejecuciones->real->porId('hosp-norte', $id)?->resultados() ?? []), 'almacen' => count($e->almacen->contenido)];
$reporte = static function (array $datos, string $clave = 'car-clave-0001', ?callable $antes = null, string $usuario = 'pipeline-ci', ?int $ejecucion = null) use ($foto): callable {
    return static function () use ($datos, $clave, $antes, $usuario, $ejecucion, $foto): array {
        $e = new Escenario();
        $id = $e->ejecucionEnCurso();
        if ($antes !== null) {
            $antes($e, $id);
        }

        return $foto($e->uc['reportar']->ejecutar($usuario, 'hosp-norte', $ejecucion ?? $id, $clave, $datos), $e, $id);
    };
};
$ev = static fn (string $tipo = 'traza', string $bytes = 'traza de prueba'): array => [['tipo' => $tipo, 'bytes' => $bytes]];
$ok = ['escenario' => 'E2E-101', 'resultado' => 'PASA', 'intentos' => 1];
$cerrada = static function (Escenario $e, int $id): void {
    $e->completar($id);
};
$yaReportado = static function (Escenario $e, int $id): void {
    $e->reportar($id, 'E2E-104', 'FALLA', 1, null, 'car-previa-001');
};
$caido = static function (Escenario $e): void {
    $e->almacen->caido = true;
};

$despliegue = static function (array $cambios, ?callable $preparar = null): callable {
    return static function () use ($cambios, $preparar): array {
        $e = new Escenario();
        $id = $e->ejecucionEnCurso();
        $e->completar($id);
        if ($preparar !== null) {
            $preparar($e, $id);
        }
        $s = $e->uc['desplegar']->ejecutar('ops-luis', 'hosp-norte', '2.4.0', $cambios + ['digest' => Escenario::DIGEST, 'entorno' => 'produccion', 'guia' => 'guia-2026.1', 'smoke' => 'PASA']);

        return ['tipo' => $s->tipo, 'codigo' => $s->codigo, 'datos' => array_diff_key($s->datos, ['id' => 1])];
    };
};
$aprobar = static function (Escenario $e, int $id): void {
    $e->uc['gate']->ejecutar('resp-release', 'hosp-norte', '2.4.0', $id);
};

return [
    'R01 PASA al primer intento' => $reporte($ok),
    'R02 PASA con 2 intentos y traza → INESTABLE' => $reporte(['intentos' => 2, 'evidencias' => $ev()] + $ok),
    'R03 FALLA con traza' => $reporte(['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1, 'evidencias' => $ev()]),
    'R04 FALLA sin evidencia (se acepta; la regla d la ve el gate)' => $reporte(['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1]),
    'R05 OMITIDO con 0 intentos' => $reporte(['escenario' => 'E2E-105', 'resultado' => 'OMITIDO', 'intentos' => 0]),
    'R06 resultado desconocido' => $reporte(['resultado' => 'QUIZAS'] + $ok),
    'R07 INESTABLE como entrada' => $reporte(['resultado' => 'INESTABLE', 'intentos' => 2] + $ok),
    'R08 6 intentos' => $reporte(['intentos' => 6] + $ok),
    'R09 intentos que no son entero' => $reporte(['intentos' => '1'] + $ok),
    'R10 PASA con 0 intentos' => $reporte(['intentos' => 0] + $ok),
    'R11 clave demasiado corta' => $reporte($ok, 'corta'),
    'R12 clave con caracteres inválidos' => $reporte($ok, 'clave con espacios'),
    'R13 misma clave y mismo contenido' => $reporte(['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1, 'evidencias' => [['tipo' => 'traza', 'bytes' => Escenario::TRAZA . 'E2E-104']]],
        'car-previa-001', $yaReportado),
    'R14 misma clave y otro contenido' => $reporte($ok, 'car-previa-001', $yaReportado),
    'R15 escenario fuera del plan' => $reporte(['escenario' => 'E2E-999'] + $ok),
    'R16 escenario ya reportado con otra clave' => $reporte(['escenario' => 'E2E-104', 'resultado' => 'PASA', 'intentos' => 1], 'car-clave-0002', $yaReportado),
    'R17 ejecución inexistente' => $reporte($ok, ejecucion: 999),
    'R18 ejecución completada' => $reporte($ok, 'car-clave-0003', $cerrada),
    'R19 tipo de evidencia no permitido' => $reporte(['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1, 'evidencias' => $ev('pdf')]),
    'R20 evidencia vacía' => $reporte(['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1, 'evidencias' => $ev('log', '')]),
    'R21 evidencia de más de 20 MB' => $reporte(['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1, 'evidencias' => $ev('video', str_repeat('v', 20 * 1024 * 1024 + 1))]),
    'R22 almacén caído' => $reporte(['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1, 'evidencias' => $ev()], 'car-clave-0004', $caido),
    'R23 sin permiso' => $reporte($ok, usuario: 'resp-release'),
    'R24 combinación: clave corta + ejecución inexistente' => $reporte($ok, 'corta', ejecucion: 999),
    'R25 combinación: resultado inválido + escenario fuera del plan' => $reporte(['escenario' => 'E2E-999', 'resultado' => 'QUIZAS', 'intentos' => 1]),
    'R26 combinación: evidencia inválida + escenario ya reportado' => $reporte(['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1, 'evidencias' => $ev('pdf')],
        'car-clave-0005', $yaReportado),
    'R27 combinación: ejecución completada + resultado inválido' => $reporte(['resultado' => 'QUIZAS'] + $ok, 'car-clave-0006', $cerrada),
    'R28 combinación: almacén caído + escenario fuera del plan' => $reporte(['escenario' => 'E2E-999', 'resultado' => 'FALLA', 'intentos' => 1, 'evidencias' => $ev()], 'car-clave-0007', $caido),
    'D01 desplegar sin evaluación' => $despliegue([]),
    'D02 desplegar aprobada' => $despliegue([], $aprobar),
    'D03 desplegar con otro digest' => $despliegue(['digest' => 'sha256:' . str_repeat('9', 64)], $aprobar),
    'D04 desplegar con la aprobación vencida' => $despliegue([], static function (Escenario $e, int $id) use ($aprobar): void {
        $aprobar($e, $id);
        $e->reloj->avanzar('+73 hours');
    }),
    'D05 desplegar con el último RECHAZADO' => $despliegue([], static function (Escenario $e, int $id) use ($aprobar): void {
        $aprobar($e, $id);
        $e->reloj->avanzar('+25 hours');
        $aprobar($e, $id);
    }),
    'D06 desplegar con guía sin versión y smoke inválido' => $despliegue(['guia' => 'la de siempre', 'smoke' => 'TAL_VEZ'], $aprobar),
    'D07 desplegar con smoke que falla' => $despliegue(['smoke' => 'FALLA'], $aprobar),
];
