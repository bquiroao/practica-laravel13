<?php

declare(strict_types=1);

namespace Pacientes\Cliente;

/** HTTP real con los streams de PHP (sin extensiones). Prototipo: en producción, conexión persistente. */
final class TransporteHttpStream implements Transporte
{
    public function __construct(private readonly string $urlBase)
    {
    }

    public function enviar(PeticionHttp $peticion, float $timeoutSegundos): RespuestaHttp
    {
        $cabeceras = $peticion->cabeceras + ['Accept' => 'application/json'];
        $contenido = null;
        if ($peticion->cuerpo !== null) {
            $contenido = (string) json_encode($peticion->cuerpo, JSON_UNESCAPED_UNICODE);
            $cabeceras['Content-Type'] = 'application/json';
        }
        $lineas = [];
        foreach ($cabeceras as $nombre => $valor) {
            $lineas[] = $nombre . ': ' . $valor;
        }

        $contexto = stream_context_create(['http' => array_filter([
            'method' => $peticion->metodo,
            'header' => implode("\r\n", $lineas),
            'content' => $contenido,
            'timeout' => $timeoutSegundos,
            'ignore_errors' => true, // 4xx/5xx también son respuestas
        ], static fn ($v): bool => $v !== null)]);

        $url = rtrim($this->urlBase, '/') . $peticion->ruta . ($peticion->consulta === [] ? '' : '?' . http_build_query($peticion->consulta));
        $cuerpo = @file_get_contents($url, false, $contexto);
        // PHP 8.4+: http_get_last_response_headers(); PHP 8.2/8.3: la variable local $http_response_header.
        $crudas = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        if ($cuerpo === false || $crudas === []) {
            throw new FallaTransporte('Sin respuesta de ' . $url);
        }

        $estado = 0;
        $respuesta = [];
        foreach ($crudas as $linea) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $linea, $m) === 1) {
                $estado = (int) $m[1];
                $respuesta = [];
            } elseif (str_contains($linea, ':')) {
                [$nombre, $valor] = explode(':', $linea, 2);
                $respuesta[trim($nombre)] = trim($valor);
            }
        }
        $json = json_decode($cuerpo, true);

        return new RespuestaHttp($estado, $respuesta, is_array($json) ? $json : []);
    }
}
