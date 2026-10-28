<?php

declare(strict_types=1);

namespace Prescripciones\Cliente;

/** HTTP con los streams de PHP, sin extensiones ni dependencias. */
final class TransporteHttpStream implements Transporte
{
    public function __construct(private readonly string $base, private readonly float $limiteSegundos = 5.0)
    {
    }

    public function enviar(string $metodo, string $ruta, array $cabeceras, ?array $cuerpo): array
    {
        $cabeceras += ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $contexto = stream_context_create(['http' => [
            'method' => $metodo,
            'header' => implode("\r\n", array_map(static fn (string $k, string $v): string => "$k: $v", array_keys($cabeceras), $cabeceras)),
            'content' => $cuerpo === null ? '' : (string) json_encode($cuerpo),
            'timeout' => $this->limiteSegundos,
            'ignore_errors' => true,
        ]]);
        $texto = @file_get_contents(rtrim($this->base, '/') . $ruta, false, $contexto);
        if ($texto === false || !isset($http_response_header[0])) {
            throw new FallaTransporte("Sin respuesta de {$this->base}");
        }
        preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m);
        $recibidas = [];
        foreach (array_slice($http_response_header, 1) as $linea) {
            if (str_contains($linea, ':')) {
                [$k, $v] = explode(':', $linea, 2);
                $recibidas[strtolower(trim($k))] = trim($v);
            }
        }
        $json = json_decode($texto, true);

        return ['estado' => (int) ($m[1] ?? 0), 'cabeceras' => $recibidas, 'cuerpo' => is_array($json) ? $json : []];
    }
}
