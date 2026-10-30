<?php

declare(strict_types=1);

namespace E2e\Cliente;

/** HTTP con los streams de PHP (sin cURL ni dependencias), con un límite de tiempo. */
final class TransporteHttpStream implements Transporte
{
    public function __construct(private readonly string $base, private readonly float $limiteSegundos = 5.0)
    {
    }

    public function enviar(string $metodo, string $ruta, array $cabeceras, string $cuerpo): array
    {
        $lineas = ['Content-Type: application/json'];
        foreach ($cabeceras as $k => $v) {
            $lineas[] = "$k: $v";
        }
        $contexto = stream_context_create(['http' => ['method' => $metodo, 'header' => implode("\r\n", $lineas), 'content' => $cuerpo,
            'timeout' => $this->limiteSegundos, 'ignore_errors' => true]]);
        $texto = @file_get_contents($this->base . $ruta, false, $contexto);
        $estado = isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m) === 1 ? (int) $m[1] : 0;
        if ($texto === false || $estado === 0) {
            throw new FallaTransporte('sin respuesta del servidor');
        }
        $datos = json_decode($texto, true);

        return ['estado' => $estado, 'cuerpo' => is_array($datos) ? $datos : []];
    }
}
