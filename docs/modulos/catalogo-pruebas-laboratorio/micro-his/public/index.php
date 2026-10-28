<?php
declare(strict_types=1);

// Front controller MVC. Uso: php -S 127.0.0.1:8081 -t public  →  http://127.0.0.1:8081/pruebas
require __DIR__ . '/../bootstrap.php';

use Lab\Presentation\Http\Vista;

$respuesta = crearRouter()->despachar(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
    $_GET,
    $_POST,
);
http_response_code($respuesta->estado);
echo Vista::render($respuesta);
