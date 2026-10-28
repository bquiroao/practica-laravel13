<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Dominio\Modelo\UsuarioClinico;
use NotasSoap\Dominio\Puertos\VerificadorAcceso;

/** Doble: un error inesperado con detalles internos que NO deben llegar al cliente. */
final class VerificadorAccesoQueExplota implements VerificadorAcceso
{
    public function puedeEscribir(UsuarioClinico $usuario, string $episodioId): bool
    {
        throw new \LogicException('fallo inesperado con detalle interno: tabla care_assignments');
    }
}
