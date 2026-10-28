<?php

declare(strict_types=1);

namespace NotasSoap\Cliente;

/** HTTP con las funciones de streams de PHP (sin cURL ni Composer). */
final class TransporteHttpStream implements Transporte
{
    public function enviar(string $metodo, string $url, array $cabeceras, ?array $cuerpo, float $timeoutSegundos): RespuestaHttp
    {
        $lineas = [];
        foreach ($cabeceras + ['Accept' => 'application/json', 'Content-Type' => 'application/json'] as $nombre => $valor) {
            $lineas[] = $nombre . ': ' . $valor;
        }
        $contexto = stream_context_create(['http' => [
            'method' => $metodo,
            'header' => implode("\r\n", $lineas),
            'content' => $cuerpo === null ? '' : (string) json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
            'timeout' => $timeoutSegundos,
            'ignore_errors' => true, // 4xx y 5xx también son respuestas
        ]]);

        $texto = @file_get_contents($url, false, $contexto);
        $crudas = $http_response_header ?? [];
        if ($texto === false || $crudas === []) {
            throw new FallaTransporte('Sin respuesta de ' . $url);
        }

        $estado = preg_match('#^HTTP/\S+\s+(\d{3})#', $crudas[0], $m) === 1 ? (int) $m[1] : 0;
        $leidas = [];
        foreach (array_slice($crudas, 1) as $linea) {
            if (str_contains($linea, ':')) {
                [$n, $v] = explode(':', $linea, 2);
                $leidas[strtolower(trim($n))] = trim($v);
            }
        }
        $json = json_decode($texto, true);

        return new RespuestaHttp($estado, $leidas, is_array($json) ? $json : null);
    }
}
