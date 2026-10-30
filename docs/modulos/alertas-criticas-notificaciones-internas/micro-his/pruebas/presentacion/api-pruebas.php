<?php
/** API REST JSON v1: autenticación, permisos por rol, tenant del token, errores y X-Request-Id. @var Pruebas $t */
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use AlertasCriticas\Persistence\{InMemoryAlertaRepository, NotificadorSimulado, RelojFijo};
use AlertasCriticas\Presentation\Api\{ApiAlertas, AutenticadorFicticio};
use AlertasCriticas\Presentation\Web\Peticion;

$api = function (): array {
    $reloj = new RelojFijo('2026-10-29 10:00:00');
    $api = new ApiAlertas(contenedorAlertas(['reloj' => $reloj, 'repositorio' => new InMemoryAlertaRepository(),
        'notificador' => new NotificadorSimulado()]), new AutenticadorFicticio());
    return [$api, $reloj];
};
$llamar = function (ApiAlertas $api, string $metodo, string $ruta, ?string $token, array $cuerpo = [], array $extra = []): array {
    $cab = $extra + ($token ? ['authorization' => "Bearer $token"] : []);
    $r = $api->atender(new Peticion($metodo, $ruta, [], [], [], $cab, $cuerpo ? json_encode($cuerpo) : ''));
    return [$r->estado, json_decode($r->cuerpo, true), $r->cabeceras];
};
$evento = ['codigo_paciente' => 'PAC-00451', 'servicio' => 'MED-INT', 'id_origen' => 'LAB-77801', 'prioridad' => 'CRITICA',
    'descripcion' => 'Potasio sérico 6.8 mmol/L (ref. 3.5-5.1)'];

$t->prueba('API sin token → 401 con formato único de error', function () use ($t, $api, $llamar) {
    [$estado, $json, $cab] = $llamar($api()[0], 'GET', '/api/v1/alertas', null);
    $t->igual([401, 'no_autenticado', 'Bearer'], [$estado, $json['error']['codigo'], $cab['WWW-Authenticate']]);
    $t->verdadero(strlen($json['error']['request_id']) >= 8, 'Falta request_id');
});

$t->prueba('API POST /alertas con token de laboratorio → 201, Location y luego 200 duplicada', function () use ($t, $api, $llamar, $evento) {
    [$a] = $api();
    [$e1, $j1, $c1] = $llamar($a, 'POST', '/api/v1/alertas', 'tok-lab-gua', $evento);
    [$e2, $j2] = $llamar($a, 'POST', '/api/v1/alertas', 'tok-lab-gua', $evento);
    $t->igual([201, 'ENVIADA', false, 200, true], [$e1, $j1['datos']['alerta']['estado'], $j1['datos']['duplicada'], $e2, $j2['datos']['duplicada']]);
    $t->igual('/api/v1/alertas/' . $j1['datos']['alerta']['id'], $c1['Location']);
});

$t->prueba('API el tenant sale del token: un tenant en el cuerpo se ignora', function () use ($t, $api, $llamar, $evento) {
    [$a] = $api();
    [, $j] = $llamar($a, 'POST', '/api/v1/alertas', 'tok-lab-gua', $evento + ['tenant' => 'HOSP-XEL-02']);
    [$estado] = $llamar($a, 'GET', '/api/v1/alertas/' . $j['datos']['alerta']['id'], 'tok-enf-gua');
    [$estadoXel] = $llamar($a, 'GET', '/api/v1/alertas/' . $j['datos']['alerta']['id'], 'tok-enf-xel');
    $t->igual([200, 404], [$estado, $estadoXel]);
});

$t->prueba('API permisos: enfermería no registra (403) y el laboratorio no consulta (403)', function () use ($t, $api, $llamar, $evento) {
    [$a] = $api();
    [$e1, $j1] = $llamar($a, 'POST', '/api/v1/alertas', 'tok-enf-gua', $evento);
    [$e2] = $llamar($a, 'GET', '/api/v1/alertas', 'tok-lab-gua');
    $t->igual([403, 'sin_permiso', 403], [$e1, $j1['error']['codigo'], $e2]);
});

$t->prueba('API RF-08: enfermería no cierra (403); el médico sí, con nota', function () use ($t, $api, $llamar, $evento) {
    [$a] = $api();
    [, $j] = $llamar($a, 'POST', '/api/v1/alertas', 'tok-lab-gua', $evento);
    $id = $j['datos']['alerta']['id'];
    $llamar($a, 'POST', "/api/v1/alertas/$id/acuse", 'tok-enf-gua');
    [$e1] = $llamar($a, 'POST', "/api/v1/alertas/$id/cierre", 'tok-enf-gua', ['nota' => 'x']);
    [$e2, $j2] = $llamar($a, 'POST', "/api/v1/alertas/$id/cierre", 'tok-med-gua', ['nota' => 'Atendida (ficticio)']);
    $t->igual([403, 200, 'CERRADA'], [$e1, $e2, $j2['datos']['alerta']['estado']]);
});

$t->prueba('API acuse idempotente: cambio true y luego false', function () use ($t, $api, $llamar, $evento) {
    [$a] = $api();
    [, $j] = $llamar($a, 'POST', '/api/v1/alertas', 'tok-lab-gua', $evento);
    $id = $j['datos']['alerta']['id'];
    [, $j1] = $llamar($a, 'POST', "/api/v1/alertas/$id/acuse", 'tok-enf-gua');
    [, $j2] = $llamar($a, 'POST', "/api/v1/alertas/$id/acuse", 'tok-enf-gua');
    $t->igual([true, false], [$j1['datos']['cambio'], $j2['datos']['cambio']]);
});

$t->prueba('API errores: RN-01 → 422 con regla, RN-07 → 403, JSON inválido → 400', function () use ($t, $api, $llamar, $evento) {
    [$a] = $api();
    [$e1, $j1] = $llamar($a, 'POST', '/api/v1/alertas', 'tok-lab-gua', ['codigo_paciente' => 'Juan'] + $evento);
    [, $j] = $llamar($a, 'POST', '/api/v1/alertas', 'tok-lab-gua', $evento);
    [$e2, $j2] = $llamar($a, 'POST', "/api/v1/alertas/{$j['datos']['alerta']['id']}/acuse", 'tok-jefe-gua');
    $r = $a->atender(new Peticion('POST', '/api/v1/alertas', [], [], [], ['authorization' => 'Bearer tok-lab-gua'], '{malo'));
    $t->igual([422, 'RN-01', 403, 'RN-07', 400], [$e1, $j1['error']['regla'], $e2, $j2['error']['regla'], $r->estado]);
});

$t->prueba('API escalamiento solo con token de planificador y reloj fijo', function () use ($t, $api, $llamar, $evento) {
    [$a, $reloj] = $api();
    $llamar($a, 'POST', '/api/v1/alertas', 'tok-lab-gua', $evento);
    [$e1] = $llamar($a, 'POST', '/api/v1/escalamientos', 'tok-enf-gua');
    $reloj->avanzarMinutos(15);
    [$e2, $j2] = $llamar($a, 'POST', '/api/v1/escalamientos', 'tok-plan');
    $t->igual([403, 200, 1], [$e1, $e2, $j2['datos']['escaladas']]);
});

$t->prueba('API X-Request-Id: se respeta el entrante válido y se genera si falta o es inválido', function () use ($t, $api, $llamar) {
    [$a] = $api();
    [, , $c1] = $llamar($a, 'GET', '/api/v1/alertas', 'tok-enf-gua', [], ['x-request-id' => 'prueba-12345678']);
    [, , $c2] = $llamar($a, 'GET', '/api/v1/alertas', 'tok-enf-gua', [], ['x-request-id' => '<script>']);
    $t->igual('prueba-12345678', $c1['X-Request-Id']);
    $t->verdadero((bool) preg_match('/^[0-9a-f]{16}$/', $c2['X-Request-Id']), 'Debe generarse uno nuevo');
});

$t->prueba('API rutas desconocidas → 404 y método incorrecto → 405 en JSON', function () use ($t, $api, $llamar) {
    [$a] = $api();
    [$e1, $j1] = $llamar($a, 'GET', '/api/v1/nada', 'tok-enf-gua');
    [$e2, $j2] = $llamar($a, 'DELETE', '/api/v1/alertas', 'tok-enf-gua');
    $t->igual([404, 'ruta_inexistente', 405, 'metodo_no_permitido'], [$e1, $j1['error']['codigo'], $e2, $j2['error']['codigo']]);
});
