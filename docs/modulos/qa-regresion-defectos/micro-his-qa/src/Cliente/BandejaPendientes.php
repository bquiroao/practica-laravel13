<?php

declare(strict_types=1);

namespace Qa\Cliente;

/**
 * Resultados que el tester ya ejecutó y el servidor todavía no confirmó. En la SPA vive en el almacenamiento local del
 * navegador; aquí, en memoria. Se guarda la petición COMPLETA con su Idempotency-Key, para reenviarla idéntica.
 */
interface BandejaPendientes
{
    /** @param array<string, mixed> $cuerpo */
    public function guardar(string $clave, string $ruta, array $cuerpo): void;

    public function quitar(string $clave): void;

    /** @return array<string, array{ruta: string, cuerpo: array<string, mixed>}> clave => petición */
    public function todos(): array;
}
