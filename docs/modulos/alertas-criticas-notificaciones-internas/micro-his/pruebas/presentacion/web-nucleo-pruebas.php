<?php
/** Núcleo MVC: router, vista con escape y sesión de demostración. @var Pruebas $t */
declare(strict_types=1);

use AlertasCriticas\Presentation\Web\{Peticion, Respuesta, Router, SesionDemo, Vista};

$router = function (): Router {
    $r = new Router();
    $r->agregar('GET', '/alertas/{id}', fn (Peticion $p, string $id) => new Respuesta(200, "detalle $id"));
    $r->agregar('POST', '/alertas/{id}/acuse', fn (Peticion $p, string $id) => new Respuesta(303, "acuse $id"));
    return $r;
};

$t->prueba('Router resuelve el parámetro {id}', function () use ($t, $router) {
    $r = $router()->despachar(new Peticion('GET', '/alertas/abc-123'));
    $t->igual([200, 'detalle abc-123'], [$r->estado, $r->cuerpo]);
});

$t->prueba('Router distingue 404 (ruta inexistente) de 405 (método no permitido)', function () use ($t, $router) {
    $t->igual(404, $router()->despachar(new Peticion('GET', '/no-existe'))->estado);
    $t->igual(405, $router()->despachar(new Peticion('GET', '/alertas/abc/acuse'))->estado);
});

$t->prueba('Router no deja que {id} cruce barras', function () use ($t, $router) {
    $t->igual(404, $router()->despachar(new Peticion('GET', '/alertas/a/b'))->estado);
});

$t->prueba('Vista escapa HTML en la salida (sin XSS)', function () use ($t) {
    $html = (new Vista(__DIR__ . '/../dobles/plantillas'))->render('parrafo', ['texto' => '<script>alert("x")</script>'], 'Tít<ulo>');
    $t->verdadero(str_contains($html, '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;'), $html);
    $t->verdadero(!str_contains($html, '<script>'), 'No debe quedar la etiqueta sin escapar');
    $t->verdadero(str_contains($html, '<title>Tít&lt;ulo&gt;</title>'), $html);
});

$t->prueba('Respuesta::redirigir usa 303 y Location', function () use ($t) {
    $r = Respuesta::redirigir('/alertas/x');
    $t->igual([303, '/alertas/x'], [$r->estado, $r->cabeceras['Location']]);
});

$t->prueba('SesionDemo ignora usuarios desconocidos y usa el de omisión', function () use ($t) {
    $s = SesionDemo::desde(new Peticion('GET', '/', [], [], ['usuario_demo' => 'admin']));
    $t->igual(['enf.turno01', 'HOSP-GUA-01'], [$s->usuario, $s->tenant()]);
    $s = SesionDemo::desde(new Peticion('GET', '/', [], [], ['usuario_demo' => 'enf.turno21']));
    $t->igual(['HOSP-XEL-02', 'enfermeria'], [$s->tenant(), $s->rol()]);
});
