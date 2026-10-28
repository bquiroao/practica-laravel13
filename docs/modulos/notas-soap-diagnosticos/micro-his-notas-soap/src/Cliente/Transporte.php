<?php

declare(strict_types=1);

namespace NotasSoap\Cliente;

/** Cómo viaja la petición. En producción, HTTP; en pruebas, un doble que guiona respuestas y fallas. */
interface Transporte
{
    /**
     * @param array<string, string> $cabeceras
     * @param array<string, mixed>|null $cuerpo
     * @throws FallaTransporte si no hubo respuesta
     */
    public function enviar(string $metodo, string $url, array $cabeceras, ?array $cuerpo, float $timeoutSegundos): RespuestaHttp;
}
