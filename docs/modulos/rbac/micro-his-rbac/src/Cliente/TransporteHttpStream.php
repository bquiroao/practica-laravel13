<?php

declare(strict_types=1);

namespace Rbac\Cliente;

/** Transporte HTTP real con los streams de PHP (sin extensiones extra). */
final class TransporteHttpStream implements Transporte
{
    public function enviar(string $metodo, string $url, array $cabeceras, ?string $cuerpo, float $timeoutSegundos): RespuestaHttp
    {
        $lineas = [];
        foreach ($cabeceras as $nombre => $valor) {
            $lineas[] = $nombre . ': ' . $valor;
        }

        $contexto = stream_context_create(['http' => [
            'method' => $metodo,
            'header' => implode("\r\n", $lineas),
            'content' => $cuerpo ?? '',
            'timeout' => $timeoutSegundos,
            'ignore_errors' => true, // devolver el cuerpo también en 4xx/5xx
        ]]);

        $http_response_header = [];
        $respuesta = @file_get_contents($url, false, $contexto);
        // PHP 8.4+: http_get_last_response_headers(); antes, la variable local $http_response_header.
        $crudas = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : $http_response_header;
        if ($respuesta === false || $crudas === []) {
            throw new FallaTransporte('Sin respuesta de ' . $url);
        }

        $estado = preg_match('#^HTTP/\S+\s+(\d{3})#', $crudas[0], $m) === 1 ? (int) $m[1] : 0;
        $leidas = [];
        foreach (array_slice($crudas, 1) as $linea) {
            [$nombre, $valor] = array_pad(explode(':', $linea, 2), 2, '');
            $leidas[strtolower(trim($nombre))] = trim($valor);
        }

        return new RespuestaHttp($estado, $respuesta, $leidas);
    }
}
