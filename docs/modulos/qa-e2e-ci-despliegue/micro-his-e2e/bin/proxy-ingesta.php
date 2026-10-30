<?php

declare(strict_types=1);

/*
 * Semana 5 · PROTOTIPO de la frontera candidata: un «servicio de ingesta» separado que recibe los reportes del CI y los
 * reenvía a la API del módulo. No es infraestructura de producción: existe solo para MEDIR cuánto cuesta el salto extra
 * y qué pasa cuando la API está caída (bin/medir.php).
 *   E2E_API=http://127.0.0.1:8092 php -S 127.0.0.1:8093 bin/proxy-ingesta.php
 */

$destino = (string) getenv('E2E_API');
$cabeceras = [];
foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'HTTP_') && in_array($k, ['HTTP_AUTHORIZATION', 'HTTP_X_TENANT_ID', 'HTTP_IDEMPOTENCY_KEY', 'HTTP_X_REQUEST_ID'], true)) {
        $cabeceras[] = str_replace('_', '-', ucwords(strtolower(substr($k, 5)), '_')) . ": $v";
    }
}
$contexto = stream_context_create(['http' => ['method' => $_SERVER['REQUEST_METHOD'], 'header' => implode("\r\n", ['Content-Type: application/json', ...$cabeceras]),
    'content' => (string) file_get_contents('php://input'), 'timeout' => 3.0, 'ignore_errors' => true]]);
$cuerpo = @file_get_contents($destino . $_SERVER['REQUEST_URI'], false, $contexto);
$estado = isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m) === 1 ? (int) $m[1] : 0;
header('Content-Type: application/json');
if ($cuerpo === false || $estado === 0) {
    http_response_code(503);
    echo '{"error":"ERROR_PERSISTENCIA"}';
    return;
}
http_response_code($estado);
echo $cuerpo;
