<?php
/** Semana 15 · Pruebas de CAJA NEGRA por la API v1 (solo entradas y salidas del contrato). @var Pruebas $t */
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use AlertasCriticas\Persistence\{InMemoryAlertaRepository, NotificadorSimulado, RelojFijo};
use AlertasCriticas\Presentation\Api\{ApiAlertas, AutenticadorFicticio};
use AlertasCriticas\Presentation\Web\Peticion;

$nuevaApi = function (): array {
    $reloj = new RelojFijo('2026-10-29 10:00:00');
    return [new ApiAlertas(contenedorAlertas(['reloj' => $reloj, 'repositorio' => new InMemoryAlertaRepository(),
        'notificador' => new NotificadorSimulado()]), new AutenticadorFicticio()), $reloj];
};
$pedir = function (ApiAlertas $api, string $m, string $ruta, ?string $tok, ?string $cuerpo = null): array {
    $r = $api->atender(new Peticion($m, $ruta, [], [], [], $tok ? ['authorization' => "Bearer $tok"] : [], $cuerpo ?? ''));
    return [$r->estado, json_decode($r->cuerpo, true)];
};
$evento = fn (array $c = []) => json_encode($c + ['codigo_paciente' => 'PAC-00451', 'servicio' => 'MED-INT',
    'id_origen' => 'LAB-' . bin2hex(random_bytes(3)), 'prioridad' => 'CRITICA', 'descripcion' => 'Potasio 6.8']);

$t->prueba('CN-01 [RF-01] equivalencia de prioridad: CRITICA y alta válidas; BAJA y vacía inválidas', function () use ($t, $nuevaApi, $pedir, $evento) {
    [$api] = $nuevaApi();
    $estados = array_map(fn ($p) => $pedir($api, 'POST', '/api/v1/alertas', 'tok-lab-gua', $evento(['prioridad' => $p]))[0], ['CRITICA', 'alta', 'BAJA', '']);
    $t->igual([201, 201, 422, 422], $estados);
});

$t->prueba('CN-02 [RF-01] límites: código PAC de 5 dígitos válido (4 y 6 no); descripción 200 válida (201 no)', function () use ($t, $nuevaApi, $pedir, $evento) {
    [$api] = $nuevaApi();
    $c = fn (array $x) => $pedir($api, 'POST', '/api/v1/alertas', 'tok-lab-gua', $evento($x))[0];
    $t->igual([201, 422, 422, 201, 422], [$c(['codigo_paciente' => 'PAC-00451']), $c(['codigo_paciente' => 'PAC-0045']),
        $c(['codigo_paciente' => 'PAC-004510']), $c(['descripcion' => str_repeat('a', 200)]), $c(['descripcion' => str_repeat('a', 201)])]);
});

$t->prueba('CN-03 [RF-07, RN-04] límites de plazo ALTA: a los 29 min no escala, a los 30 sí', function () use ($t, $nuevaApi, $pedir, $evento) {
    [$api, $reloj] = $nuevaApi();
    $pedir($api, 'POST', '/api/v1/alertas', 'tok-lab-gua', $evento(['prioridad' => 'ALTA']));
    $reloj->avanzarMinutos(29);
    [, $j29] = $pedir($api, 'POST', '/api/v1/escalamientos', 'tok-plan');
    $reloj->avanzarMinutos(1);
    [, $j30] = $pedir($api, 'POST', '/api/v1/escalamientos', 'tok-plan');
    $t->igual([0, 1], [$j29['datos']['escaladas'], $j30['datos']['escaladas']]);
});

$t->prueba('CN-04 [RNF-01, RF-08] permisos por rol (tabla de decisión)', function () use ($t, $nuevaApi, $pedir, $evento) {
    [$api] = $nuevaApi();
    $esperado = ['tok-lab-gua' => [201, 403], 'tok-enf-gua' => [403, 200], 'tok-med-gua' => [403, 200], 'tok-plan' => [403, 403]];
    $real = [];
    foreach (array_keys($esperado) as $tok) {
        $real[$tok] = [$pedir($api, 'POST', '/api/v1/alertas', $tok, $evento())[0], $pedir($api, 'GET', '/api/v1/alertas', $tok)[0]];
    }
    $t->igual($esperado, $real);
});

$t->prueba('CN-05 errores del contrato: 401, 400, 404, 405 y 422, todos con codigo y request_id', function () use ($t, $nuevaApi, $pedir) {
    [$api] = $nuevaApi();
    $casos = [[null, 'GET', '/api/v1/alertas', null, 401], ['tok-lab-gua', 'POST', '/api/v1/alertas', '[1,2', 400],
        ['tok-enf-gua', 'GET', '/api/v1/alertas/00000000-0000-4000-8000-000000000000', null, 404],
        ['tok-enf-gua', 'PUT', '/api/v1/alertas', null, 405], ['tok-lab-gua', 'POST', '/api/v1/alertas', '{}', 422]];
    foreach ($casos as [$tok, $m, $ruta, $cuerpo, $esp]) {
        [$estado, $j] = $pedir($api, $m, $ruta, $tok, $cuerpo);
        $t->igual($esp, $estado, "$m $ruta");
        $t->verdadero(isset($j['error']['codigo'], $j['error']['request_id']), "Formato de error en $m $ruta");
    }
});
