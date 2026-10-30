<?php
declare(strict_types=1);

// Front controller: php -S 127.0.0.1:8042 -t public   →   http://127.0.0.1:8042/pacientes/PAC-00451/alerta
// API (semana 5):   http://127.0.0.1:8042/api/v1/pacientes/PAC-00451/alerta  con Authorization: Bearer tok-enf-h01
require __DIR__ . '/../bootstrap.php';

use Alergias\Presentacion\Api\KernelApi;
use Alergias\Presentacion\Web\ControladorAlerta;

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$esApi = str_starts_with($ruta, '/api/');
try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $respuesta = $esApi
        ? armarApi(armarCasosDeUso())->manejar($metodo, $ruta, array_change_key_case(getallheaders()), (string) file_get_contents('php://input'))
        : armarWeb(armarCasosDeUso())->despachar($metodo, $ruta, $_POST);
} catch (Throwable $e) {
    $respuesta = $esApi ? KernelApi::fueraDeServicio($e) : ControladorAlerta::error($e); // base caída al conectar → 503
}
http_response_code($respuesta->estado);
foreach ($respuesta->cabeceras as $nombre => $valor) {
    header("{$nombre}: {$valor}");
}
echo $respuesta->cuerpo;
