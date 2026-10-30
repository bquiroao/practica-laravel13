<?php
declare(strict_types=1);

// Entrada MVC con el adaptador InMemory: códigos HTTP, salida escapada, CSRF y PRG.

use Catalogo\Presentation\Web\ControladorMedicamentos;
use Catalogo\Presentation\Web\Csrf;
use Catalogo\Presentation\Web\Peticion;
use Catalogo\Presentation\Web\RutasWeb;
use Catalogo\Presentation\Web\Vista;

require_once __DIR__ . '/../../bootstrap.php';

$web = function (Peticion $p) {
    $c = new ControladorMedicamentos(crearAplicacion(null, new RelojFijo(), 'memoria'), new Vista(), new Csrf('token-prueba'), Fabrica::NORTE, 'qf.norte');
    return RutasWeb::crear($c)->despachar($p, $c->error(...));
};
$alta = fn (array $cambios) => new Peticion('POST', '/medicamentos', [], ['csrf' => 'token-prueba'] + $cambios + Fabrica::datos(['vigente_desde' => '']));

return [
    'GET /medicamentos?q=amoxi lista solo vigentes (200)' => function () use ($web) {
        $r = $web(new Peticion('GET', '/medicamentos', ['q' => 'amoxi']));
        Afirmar::igual(200, $r->estado, 'estado');
        Afirmar::verdadero(str_contains($r->cuerpo, 'MED-000102') && str_contains($r->cuerpo, 'MED-000103'), 'muestra las dos amoxicilinas');
    },
    'Búsqueda sin criterio muestra el formulario vacío y sin resultados' => function () use ($web) {
        $r = $web(new Peticion('GET', '/medicamentos'));
        Afirmar::igual(200, $r->estado, 'estado');
        Afirmar::verdadero(!str_contains($r->cuerpo, 'resultado(s)'), 'sin tabla');
    },
    'POST válido redirige al detalle (303, patrón PRG)' => function () use ($web, $alta) {
        $r = $web($alta(['nombre' => 'Loratadina 10 mg tableta', 'principio_activo' => 'Loratadina', 'concentracion_valor' => '10', 'forma_farmaceutica' => 'TABLETA']));
        Afirmar::igual(303, $r->estado, 'estado');
        Afirmar::igual('/medicamentos/MED-000107?aviso=registrado', $r->cabeceras['Location'], 'destino');
    },
    'POST inválido: 422, errores por campo y salida escapada (XSS)' => function () use ($web, $alta) {
        $r = $web($alta(['nombre' => '<script>alert(1)</script>', 'concentracion_valor' => '0']));
        Afirmar::igual(422, $r->estado, 'estado');
        Afirmar::verdadero(str_contains($r->cuerpo, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'valor escapado');
        Afirmar::verdadero(!str_contains($r->cuerpo, '<script>alert'), 'sin script ejecutable');
        Afirmar::verdadero(str_contains($r->cuerpo, 'id="concentracion_valor-error"'), 'error asociado al campo');
    },
    'POST duplicado: 409 con el código existente' => function () use ($web, $alta) {
        $r = $web($alta([]));
        Afirmar::igual(409, $r->estado, 'estado');
        Afirmar::verdadero(str_contains($r->cuerpo, 'MED-000102'), 'informa MED-000102');
    },
    'POST sin token CSRF: 403 y no se guarda' => function () use ($web) {
        Afirmar::igual(403, $web(new Peticion('POST', '/medicamentos', [], Fabrica::datos(['principio_activo' => 'Loratadina'])))->estado, 'estado');
    },
    'Eliminar un código usado: 409 en la misma página de detalle' => function () use ($web) {
        $r = $web(new Peticion('POST', '/medicamentos/MED-000101/eliminar', [], ['csrf' => 'token-prueba']));
        Afirmar::igual(409, $r->estado, 'estado');
        Afirmar::verdadero(str_contains($r->cuerpo, 'Historial de versiones') && str_contains($r->cuerpo, 'dé de baja'), 'detalle con el mensaje');
    },
    'Código inexistente 404 y método no permitido 405' => function () use ($web) {
        Afirmar::igual(404, $web(new Peticion('GET', '/medicamentos/MED-999999'))->estado, 'inexistente');
        Afirmar::igual(404, $web(new Peticion('GET', '/medicamentos/otra/cosa/mas'))->estado, 'ruta desconocida');
        Afirmar::igual(405, $web(new Peticion('DELETE', '/medicamentos'))->estado, 'DELETE');
    },
];
