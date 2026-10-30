<?php

declare(strict_types=1);

/*
 * Almacén de evidencias simulado como SERVICIO aparte, solo para evaluar la frontera (semana 5). No es infraestructura
 * de producción.   php -S 127.0.0.1:8094 bin/almacen-simulado.php
 * Variables: ALMACEN_DIR (carpeta), ALMACEN_RETRASO_MS (responde tarde), ALMACEN_ESTADO (p. ej. 500, falla),
 *            ALMACEN_CORROMPER=1 (confirma con otra huella: el cliente debe rechazarlo).
 *   PUT /almacen/v1/{tenant}/{sha256}  → guarda si el contenido tiene esa huella; 201 {"huella"}
 *   GET /almacen/v1/{tenant}/{sha256}  → los bytes
 */

$retraso = (int) (getenv('ALMACEN_RETRASO_MS') ?: 0);
if ($retraso > 0) {
    usleep($retraso * 1000);
}
$estado = (int) (getenv('ALMACEN_ESTADO') ?: 200);
if ($estado !== 200) {
    http_response_code($estado);
    echo '{"error":"SIMULADO"}';

    return true;
}
$ruta = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!preg_match('#^/almacen/v1/([a-z0-9-]{1,40})/([0-9a-f]{64})$#', $ruta, $m)) {
    http_response_code(404);
    echo '{"error":"RUTA_NO_ENCONTRADA"}';

    return true;
}
$dir = (getenv('ALMACEN_DIR') ?: sys_get_temp_dir() . '/almacen-simulado') . '/' . $m[1];
$archivo = "$dir/{$m[2]}";
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $bytes = (string) file_get_contents('php://input');
    if (hash('sha256', $bytes) !== $m[2]) {
        http_response_code(422);
        echo '{"error":"HUELLA_NO_COINCIDE"}';

        return true;
    }
    @mkdir($dir, 0770, true);
    $nuevo = !is_file($archivo);
    file_put_contents($archivo, $bytes, LOCK_EX);
    http_response_code($nuevo ? 201 : 200);
    header('Content-Type: application/json');
    echo json_encode(['huella' => getenv('ALMACEN_CORROMPER') ? str_repeat('0', 64) : $m[2]]);

    return true;
}
if (!is_file($archivo)) {
    http_response_code(404);
    echo '{"error":"NO_ENCONTRADA"}';

    return true;
}
header('Content-Type: application/octet-stream');
readfile($archivo);

return true;
