<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Excepciones;

/** La misma Idempotency-Key llegó con un contenido distinto: no es un reintento, es un error del cliente (422). */
final class ClaveIdempotenciaReutilizada extends \DomainException
{
}
