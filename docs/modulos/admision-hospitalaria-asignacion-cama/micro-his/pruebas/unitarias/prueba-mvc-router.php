<?php
declare(strict_types=1);

use Admision\Presentation\MapaErrores;
use Admision\Presentation\Web\Peticion;
use Admision\Presentation\Web\Respuesta;
use Admision\Presentation\Web\Router;
use Admision\Presentation\Web\Vista;
use Pruebas\Afirmar;

$router = new Router();
$router->agregar('GET', '/', fn (Peticion $p) => new Respuesta(200, 'inicio'));
$router->agregar('GET', '/admisiones/{id}', fn (Peticion $p, string $id) => new Respuesta(200, "admisión {$id}"));

return [
    'Router despacha por método y ruta, y captura {id}' => function () use ($router) {
        Afirmar::igual('inicio', $router->despachar(new Peticion('GET', '/'))->cuerpo);
        Afirmar::igual('admisión abc-1', $router->despachar(new Peticion('GET', '/admisiones/abc-1'))->cuerpo);
    },
    'Router responde 404 a rutas desconocidas y 405 a métodos no permitidos' => function () use ($router) {
        Afirmar::igual(404, $router->despachar(new Peticion('GET', '/nada'))->estado);
        Afirmar::igual(404, $router->despachar(new Peticion('GET', '/admisiones/a/b'))->estado);
        Afirmar::igual(405, $router->despachar(new Peticion('POST', '/'))->estado);
    },
    'Vista escapa lo que imprime (sin extract)' => function () {
        $carpeta = sys_get_temp_dir() . '/vista-prueba-' . getmypid();
        @mkdir($carpeta);
        file_put_contents("{$carpeta}/layout.php", '<?= $d[\'contenido\'] ?>');
        file_put_contents("{$carpeta}/saludo.php", '<p><?= $e($d[\'texto\']) ?></p>');
        $html = (new Vista($carpeta))->render('saludo', ['texto' => '<script>"x"</script>'], 't');
        array_map('unlink', glob("{$carpeta}/*.php") ?: []);
        rmdir($carpeta);
        Afirmar::igual('<p>&lt;script&gt;&quot;x&quot;&lt;/script&gt;</p>', $html);
    },
    'MapaErrores asigna un estado HTTP a cada código' => function () {
        Afirmar::igual([409, 422, 403, 503, 500], array_map([MapaErrores::class, 'estado'], ['CAMA_YA_ASIGNADA', 'VALIDACION', 'ACCESO_DENEGADO', 'ERROR_PERSISTENCIA', 'OTRO']));
    },
];
