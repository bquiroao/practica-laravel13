<?php
declare(strict_types=1);

// Front controller. Uso: php -S 127.0.0.1:8081 -t public
//   Web (MVC):  http://127.0.0.1:8081/pruebas
//   API (JSON): http://127.0.0.1:8081/api/v1/laboratorio/pruebas
require __DIR__ . '/../bootstrap.php';

use Lab\Presentation\Http\Vista;

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (str_starts_with($ruta, '/api/')) {
    $cabeceras = array_change_key_case(function_exists('getallheaders') ? getallheaders() : [], CASE_LOWER);
    crearApi()->despachar($metodo, $ruta, $cabeceras, $_GET, (string) file_get_contents('php://input'))->enviar();
    return;
}

$respuesta = crearRouter()->despachar($metodo, $ruta, $_GET, $_POST);
http_response_code($respuesta->estado);
echo Vista::render($respuesta);
