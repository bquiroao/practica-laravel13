<?php
declare(strict_types=1);

// Punto de entrada: solo despacha la petición al controlador que corresponde.
// Servidor: php -S localhost:8000 -t public public/index.php
$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (PHP_SAPI === 'cli-server' && $ruta !== '/' && is_file(__DIR__ . $ruta)) {
    return false;   // archivos estáticos, como cliente.html
}
require __DIR__ . '/../bootstrap.php';

$inicio = hrtime(true);
// Prototipo: el rol llega en la cabecera X-Rol; en el SHI sale de los claims del token JWT.
$peticion = $_GET + [
    'rol' => $_SERVER['HTTP_X_ROL'] ?? 'administrador',
    'correlacion' => preg_replace('/[^a-zA-Z0-9-]/', '', $_SERVER['HTTP_X_CORRELATION_ID'] ?? '') ?: bin2hex(random_bytes(8)),
];
$respuesta = crearEnrutador(crearCasoDeUso())->despachar($_SERVER['REQUEST_METHOD'] ?? 'GET', $ruta, $peticion);
$respuesta->enviar();

// Observabilidad: una línea JSON por petición, sin datos de pacientes.
error_log(json_encode([
    'correlationId' => $peticion['correlacion'],
    'ruta' => $ruta,
    'codigo' => $respuesta->codigo,
    'ms' => round((hrtime(true) - $inicio) / 1e6, 1),
]));
