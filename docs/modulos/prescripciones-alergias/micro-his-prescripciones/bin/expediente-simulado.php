<?php

declare(strict_types=1);

/*
 * Expediente simulado como SERVICIO aparte, solo para evaluar la frontera (semana 5). No es infraestructura de producción.
 *   php -S 127.0.0.1:8092 bin/expediente-simulado.php
 * Variables: EXPEDIENTE_RETRASO_MS (responde tarde) y EXPEDIENTE_ESTADO (p. ej. 500, para simular una falla).
 * GET /expediente/v1/pacientes/{id}/alergias → {"evaluado": bool, "alergias": [...]} con los datos de database/datos-demo.php.
 */

$retraso = (int) (getenv('EXPEDIENTE_RETRASO_MS') ?: 0);
if ($retraso > 0) {
    usleep($retraso * 1000);
}
header('Content-Type: application/json; charset=utf-8');
$estado = (int) (getenv('EXPEDIENTE_ESTADO') ?: 200);
if ($estado !== 200) {
    http_response_code($estado);
    echo '{"error":"SIMULADO"}';

    return true;
}
if (!preg_match('#^/expediente/v1/pacientes/([^/]+)/alergias$#', (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), $m)) {
    http_response_code(404);
    echo '{"error":"RUTA_NO_ENCONTRADA"}';

    return true;
}
$datos = require dirname(__DIR__) . '/database/datos-demo.php';
$lista = $datos['alergias'][rawurldecode($m[1])] ?? [];
$claves = ['codigo', 'nombre', 'tipo', 'severidad', 'estado', 'reaccion'];
echo json_encode(['evaluado' => $lista !== [], 'alergias' => array_map(static fn (array $a): array => array_combine($claves, $a), $lista)],
    JSON_UNESCAPED_UNICODE);

return true;
