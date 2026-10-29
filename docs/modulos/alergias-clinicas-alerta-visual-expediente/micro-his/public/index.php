<?php
declare(strict_types=1);

// Front controller: php -S 127.0.0.1:8042 -t public   →   http://127.0.0.1:8042/pacientes/PAC-00451/alerta
require __DIR__ . '/../bootstrap.php';

use Alergias\Presentacion\Web\ControladorAlerta;

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
try {
    $respuesta = armarWeb(armarCasosDeUso())->despachar($_SERVER['REQUEST_METHOD'] ?? 'GET', $ruta, $_POST);
} catch (Throwable $e) {
    $respuesta = ControladorAlerta::error($e); // por ejemplo, base caída al conectar → 503
}
http_response_code($respuesta->estado);
foreach ($respuesta->cabeceras as $nombre => $valor) {
    header("{$nombre}: {$valor}");
}
echo $respuesta->cuerpo;
