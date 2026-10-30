<?php
declare(strict_types=1);

// Semana 15 · Defecto D-02: si la base no responde al arrancar, el 503 de la API sale del front
// controller y no lleva X-Request-Id (RNF-05). Levanta el servidor de PHP con una base inexistente.

return [
    '[DEFECTO D-02] RNF-05 el 503 por base caída al arrancar también lleva X-Request-Id' => function () {
        $libre = stream_socket_server('tcp://127.0.0.1:0');
        $puerto = (int) substr((string) stream_socket_get_name($libre, false), strrpos((string) stream_socket_get_name($libre, false), ':') + 1);
        fclose($libre);
        $entorno = getenv() + [];
        $entorno['CATALOGO_BD_PUERTO'] = '1'; // nadie escucha en el puerto 1
        unset($entorno['CATALOGO_ALMACEN']);
        $proceso = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', __DIR__ . '/../../public'],
            [['pipe', 'r'], ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'], ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']], $tubos, null, $entorno);
        try {
            $cabeceras = [];
            for ($i = 0; $i < 30 && $cabeceras === []; $i++) {
                usleep(100000);
                $contexto = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5, 'header' => 'Authorization: Bearer tok-qf-norte-0001']]);
                if (@file_get_contents("http://127.0.0.1:$puerto/api/v1/opciones", false, $contexto) !== false) {
                    $cabeceras = $http_response_header;
                }
            }
            Afirmar::verdadero($cabeceras !== [], 'el servidor de prueba respondió');
            Afirmar::verdadero(str_contains($cabeceras[0], '503'), 'responde 503');
            $conId = array_filter($cabeceras, fn ($h) => stripos($h, 'X-Request-Id:') === 0);
            Afirmar::verdadero($conId !== [], 'lleva X-Request-Id');
        } finally {
            proc_terminate($proceso);
            proc_close($proceso);
        }
    },
];
