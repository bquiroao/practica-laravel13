<?php

declare(strict_types=1);

namespace Rbac\Cliente;

/** Envía una petición HTTP. La librería cliente no sabe si viaja por red o en proceso. */
interface Transporte
{
    /**
     * @param array<string, string> $cabeceras
     * @throws FallaTransporte si no hubo respuesta (conexión rechazada, timeout, DNS…)
     */
    public function enviar(string $metodo, string $url, array $cabeceras, ?string $cuerpo, float $timeoutSegundos): RespuestaHttp;
}
