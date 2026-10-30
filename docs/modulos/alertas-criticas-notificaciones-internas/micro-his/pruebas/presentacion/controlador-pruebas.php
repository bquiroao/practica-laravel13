<?php
/** MVC completo con dobles: rutas → controlador → caso de uso → vista. @var Pruebas $t */
declare(strict_types=1);

require_once __DIR__ . '/../dobles/RepositorioDoble.php';
require_once __DIR__ . '/../../bootstrap.php';

use AlertasCriticas\Persistence\{NotificadorSimulado, RelojFijo};
use AlertasCriticas\Presentation\Web\{AlertaController, Peticion, Router, Rutas, Vista};

$web = function (?RepositorioDoble $repo = null): Router {
    $router = new Router();
    Rutas::registrar($router, new AlertaController(contenedorAlertas([
        'reloj' => new RelojFijo('2026-10-29 10:00:00'), 'repositorio' => $repo ?? new RepositorioDoble(),
        'notificador' => new NotificadorSimulado(),
    ]), new Vista(__DIR__ . '/../../plantillas')));
    return $router;
};
$evento = fn (array $cambios = []) => $cambios + ['codigo_paciente' => 'PAC-00451', 'servicio' => 'MED-INT',
    'origen' => 'ingreso-resultados-laboratorio', 'id_origen' => 'LAB-77801', 'prioridad' => 'CRITICA',
    'descripcion' => 'Potasio sérico 6.8 mmol/L (ref. 3.5-5.1)'];
$como = fn (string $usuario) => ['usuario_demo' => $usuario];
$idDe = fn ($respuesta) => preg_replace('#^/alertas/([^?]+).*$#', '$1', $respuesta->cabeceras['Location']);

$t->prueba('GET / sin alertas muestra el estado vacío', function () use ($t, $web) {
    $r = $web()->despachar(new Peticion('GET', '/'));
    $t->igual(200, $r->estado);
    $t->verdadero(str_contains($r->cuerpo, 'No hay alertas registradas'), 'Falta el estado vacío');
});

$t->prueba('POST /alertas registra y redirige con 303 al detalle', function () use ($t, $web, $evento, $idDe) {
    $router = $web();
    $r = $router->despachar(new Peticion('POST', '/alertas', [], $evento()));
    $t->igual(303, $r->estado);
    $t->verdadero(str_ends_with($r->cabeceras['Location'], '?msg=registrada'), $r->cabeceras['Location']);
    $detalle = $router->despachar(new Peticion('GET', '/alertas/' . $idDe($r), ['msg' => 'registrada']));
    $t->verdadero(str_contains($detalle->cuerpo, 'Estado <strong>ENVIADA</strong>') && str_contains($detalle->cuerpo, 'Alerta registrada y notificada.'), 'Detalle incompleto');
});

$t->prueba('La descripción con HTML se muestra escapada (sin XSS)', function () use ($t, $web, $evento, $idDe) {
    $router = $web();
    $r = $router->despachar(new Peticion('POST', '/alertas', [], $evento(['descripcion' => '<img src=x onerror=alert(1)>'])));
    $html = $router->despachar(new Peticion('GET', '/alertas/' . $idDe($r)))->cuerpo;
    $t->verdadero(str_contains($html, '&lt;img src=x onerror=alert(1)&gt;') && !str_contains($html, '<img src=x'), 'HTML sin escapar');
});

$t->prueba('Acuse por la web: primero «acusada», después «ya-acusada»', function () use ($t, $web, $evento, $idDe, $como) {
    $router = $web();
    $id = $idDe($router->despachar(new Peticion('POST', '/alertas', [], $evento())));
    $r1 = $router->despachar(new Peticion('POST', "/alertas/$id/acuse", [], [], $como('enf.turno01')));
    $r2 = $router->despachar(new Peticion('POST', "/alertas/$id/acuse", [], [], $como('enf.turno01')));
    $t->igual(["/alertas/$id?msg=acusada", "/alertas/$id?msg=ya-acusada"], [$r1->cabeceras['Location'], $r2->cabeceras['Location']]);
});

$t->prueba('RN-07 por la web: un usuario no notificado recibe 403', function () use ($t, $web, $evento, $idDe, $como) {
    $router = $web();
    $id = $idDe($router->despachar(new Peticion('POST', '/alertas', [], $evento())));
    $t->igual(403, $router->despachar(new Peticion('POST', "/alertas/$id/acuse", [], [], $como('jefe.servicio01')))->estado);
});

$t->prueba('RNF-01 por la web: otro tenant recibe 404', function () use ($t, $web, $evento, $idDe, $como) {
    $router = $web();
    $id = $idDe($router->despachar(new Peticion('POST', '/alertas', [], $evento())));
    $t->igual(404, $router->despachar(new Peticion('GET', "/alertas/$id", [], [], $como('enf.turno21')))->estado);
});

$t->prueba('RN-01 y RN-09 por la web: datos inválidos o cierre sin nota dan 422', function () use ($t, $web, $evento, $idDe, $como) {
    $router = $web();
    $t->igual(422, $router->despachar(new Peticion('POST', '/alertas', [], $evento(['codigo_paciente' => 'Juan'])))->estado);
    $id = $idDe($router->despachar(new Peticion('POST', '/alertas', [], $evento())));
    $router->despachar(new Peticion('POST', "/alertas/$id/acuse", [], [], $como('med.tratante01')));
    $r = $router->despachar(new Peticion('POST', "/alertas/$id/cierre", [], ['nota' => '  '], $como('med.tratante01')));
    $t->igual([422, true], [$r->estado, str_contains($r->cuerpo, '[RN-09]')]);
});

$t->prueba('Error de persistencia por la web: 503 con mensaje recuperable', function () use ($t, $web, $evento) {
    $r = $web(new RepositorioDoble(true))->despachar(new Peticion('POST', '/alertas', [], $evento()));
    $t->igual([503, true], [$r->estado, str_contains($r->cuerpo, 'Intente de nuevo')]);
});

$t->prueba('El controlador no tiene SQL ni reglas de negocio (revisión estática)', function () use ($t) {
    $codigo = file_get_contents(__DIR__ . '/../../src/Presentation/Web/AlertaController.php');
    foreach (['SELECT', 'INSERT', 'UPDATE ', 'PDO', 'prepare(', 'NIVEL_MAXIMO', 'PoliticaEscalamiento', "'CRITICA'"] as $prohibido) {
        $t->verdadero(!str_contains($codigo, $prohibido), "El controlador contiene «{$prohibido}»");
    }
});
