<?php

declare(strict_types=1);

/*
 * Semana 6 · Demostración del cambio práctico R-15, de punta a punta por HTTP en proceso (adaptadores en memoria):
 * un defecto MAYOR en TC-107 (crítico en «expediente») bloquea el GO aunque todo pase; la trazabilidad lo marca antes del
 * cierre; tras corregirlo y verificarlo en una re-ejecución, la campaña siguiente da GO.
 *   php bin/demo-r15.php
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';

use Qa\Presentacion\Http\Solicitud;

$kernel = crearKernel(['persistencia' => 'memoria', 'registro_acceso' => 'ninguno'] + (require dirname(__DIR__) . '/config/config.php'));
$png = base64_encode("\x89PNG\r\n\x1a\nformato-de-fecha-incorrecto");
$n = 0;
$pedir = static function (string $titulo, string $metodo, string $ruta, string $token, ?array $cuerpo = null, ?string $clave = null, ?callable $mostrar = null) use ($kernel, &$n): array {
    $r = $kernel->manejar(new Solicitud($metodo, '/api/v1/qa' . $ruta, ['authorization' => "Bearer $token", 'x-tenant-id' => 'hosp-norte']
        + ($clave === null ? [] : ['idempotency-key' => $clave]), $cuerpo === null ? '' : json_encode($cuerpo)));
    $vista = $mostrar === null ? $r->cuerpo : $mostrar($r->cuerpo);
    printf("%d. %s\n   > %s /api/v1/qa%s\n   %d %s\n\n", ++$n, $titulo, $metodo, $ruta, $r->estado, json_encode($vista, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));

    return $r->cuerpo;
};

$pedir('Campaña sobre «expediente» (TC-107 y TC-108 críticos, TC-109 no)', 'POST', '/campanas', 'token-lider-qa', ['nombre' => 'Regresión H4', 'funcionalidades' => ['expediente'], 'casos' => ['TC-107', 'TC-108', 'TC-109']]);
$pedir('Iniciar sobre 1.4.0', 'POST', '/campanas/1/inicio', 'token-lider-qa', ['build' => '1.4.0']);
$pedir('TC-107 FALLA: la nota firmada muestra la fecha en otro formato (propuesta MAYOR)', 'POST', '/campanas/1/resultados', 'token-tito-tester', ['caso' => 'TC-107', 'resultado' => 'FALLA',
    'evidencias' => [['tipo' => 'captura', 'contenido_base64' => $png]], 'defecto' => ['titulo' => 'Fecha de la nota en formato incorrecto', 'pasos' => ['Abrir la nota firmada'], 'severidad' => 'MAYOR']], 'r15-000001');
$pedir('El Líder QA confirma MAYOR (no es CRÍTICA: no altera el contenido clínico)', 'POST', '/defectos/1/clasificacion', 'token-lider-qa', ['severidad' => 'MAYOR', 'prioridad' => 'ALTA']);
$pedir('Re-ejecución de TC-107 en la misma campaña: PASA en otra estación (el defecto sigue ABIERTO)', 'POST', '/campanas/1/resultados', 'token-tito-tester', ['caso' => 'TC-107', 'resultado' => 'PASA'], 'r15-000002');
$pedir('TC-108 pasa', 'POST', '/campanas/1/resultados', 'token-tito-tester', ['caso' => 'TC-108', 'resultado' => 'PASA'], 'r15-000003');
$pedir('TC-109 pasa', 'POST', '/campanas/1/resultados', 'token-tito-tester', ['caso' => 'TC-109', 'resultado' => 'PASA'], 'r15-000004');
$pedir('Trazabilidad ANTES de cerrar: el defecto 1 dice bloquea_go = true', 'GET', '/campanas/1/trazabilidad', 'token-producto', null, null,
    static fn (array $c): array => array_map(static fn (array $f): array => ['caso' => $f['caso'], 'ultimo' => $f['ultimo_resultado'], 'defectos' => $f['defectos']], $c['casos']));
$pedir('Cerrar: tasa 100 % y 0 críticos, pero NO_GO por R-15', 'POST', '/campanas/1/cierre', 'token-lider-qa');
$pedir('El desarrollador lo corrige en 1.4.1', 'POST', '/defectos/1/correccion', 'token-dev-luis', ['build' => '1.4.1']);
$pedir('Campaña sobre 1.4.1', 'POST', '/campanas', 'token-lider-qa', ['nombre' => 'Regresión H4 (1.4.1)', 'funcionalidades' => ['expediente'], 'casos' => ['TC-107', 'TC-108']]);
$pedir('Iniciar', 'POST', '/campanas/2/inicio', 'token-lider-qa', ['build' => '1.4.1']);
$pedir('Re-ejecución que VERIFICA el defecto 1: PASA lo cierra', 'POST', '/campanas/2/resultados', 'token-tito-tester', ['caso' => 'TC-107', 'resultado' => 'PASA', 'verifica_defecto' => 1], 'r15-000005');
$pedir('TC-108 pasa', 'POST', '/campanas/2/resultados', 'token-tito-tester', ['caso' => 'TC-108', 'resultado' => 'PASA'], 'r15-000006');
$pedir('Cerrar: GO', 'POST', '/campanas/2/cierre', 'token-lider-qa');
