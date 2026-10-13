<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Cliente\FallaTransporte;
use NotasSoap\Cliente\RespuestaHttp;
use NotasSoap\Cliente\Transporte;

/** Doble de red que devuelve, en orden, lo que se le guionó: un estado HTTP o 'falla' (sin respuesta). */
final class TransporteGuionado implements Transporte
{
    public int $llamadas = 0;

    /** @param list<int|string> $guion */
    public function __construct(private array $guion)
    {
    }

    public function enviar(string $metodo, string $url, array $cabeceras, ?array $cuerpo, float $timeoutSegundos): RespuestaHttp
    {
        $this->llamadas++;
        $paso = array_shift($this->guion) ?? 'falla';
        if ($paso === 'falla') {
            throw new FallaTransporte('sin respuesta (simulada)');
        }

        return new RespuestaHttp((int) $paso, [], $paso >= 400 ? ['error' => ['code' => 'simulado', 'message' => 'x']] : ['ok' => true]);
    }
}
