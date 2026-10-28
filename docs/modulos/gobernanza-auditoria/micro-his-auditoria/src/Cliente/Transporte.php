<?php

declare(strict_types=1);

namespace Auditoria\Cliente;

interface Transporte
{
    /** @throws FallaTransporte si no hubo respuesta dentro de $timeoutSegundos */
    public function enviar(PeticionHttp $peticion, float $timeoutSegundos): RespuestaHttp;
}
