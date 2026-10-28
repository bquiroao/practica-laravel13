<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Puertos;

/**
 * Recuerda qué nota creó cada Idempotency-Key (por hospital y usuario), para que un reintento
 * del cliente no cree una nota duplicada. Se escribe en la MISMA transacción que la nota.
 */
interface RegistroIdempotencia
{
    /** @return array{huella: string, nota_id: string}|null */
    public function buscar(string $hospitalId, string $usuarioId, string $clave): ?array;

    public function guardar(string $hospitalId, string $usuarioId, string $clave, string $huella, string $notaId): void;
}
