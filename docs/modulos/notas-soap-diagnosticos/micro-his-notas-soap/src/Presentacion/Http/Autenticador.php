<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http;

/** En el HIS lo implementa JwtAuth (módulo 1); aquí, tokens de demostración. */
interface Autenticador
{
    public function identidad(string $token): ?Identidad;
}
