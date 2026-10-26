<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Cliente\FallaTransporte;
use NotasSoap\Cliente\RespuestaHttp;
use NotasSoap\Cliente\Transporte;
use NotasSoap\Presentacion\Http\Kernel;
use NotasSoap\Presentacion\Http\Solicitud;

/**
 * Doble de red: entrega la petición al Kernel real dentro del mismo proceso.
 * Con $perderRespuestas > 0, el servidor SÍ procesa la petición pero la respuesta "se pierde"
 * en el camino (FallaTransporte), como cuando se cae la red justo después del COMMIT.
 */
final class TransporteEnProceso implements Transporte
{
    /** @var list<array{metodo: string, url: string, cabeceras: array<string, string>}> */
    public array $enviadas = [];

    public function __construct(private readonly Kernel $kernel, private int $perderRespuestas = 0)
    {
    }

    public function enviar(string $metodo, string $url, array $cabeceras, ?array $cuerpo, float $timeoutSegundos): RespuestaHttp
    {
        $this->enviadas[] = ['metodo' => $metodo, 'url' => $url, 'cabeceras' => $cabeceras];
        $partes = parse_url($url);
        parse_str($partes['query'] ?? '', $consulta);
        $r = $this->kernel->manejar(new Solicitud($metodo, $partes['path'] ?? '/', array_change_key_case($cabeceras, CASE_LOWER), $cuerpo, $consulta));
        if ($this->perderRespuestas > 0) {
            $this->perderRespuestas--;
            throw new FallaTransporte('respuesta perdida (simulada)');
        }

        return new RespuestaHttp($r->estado, array_change_key_case($r->cabeceras, CASE_LOWER), $r->cuerpo);
    }
}
