<?php

declare(strict_types=1);

namespace E2e\Cliente;

/**
 * Resultados que el pipeline no pudo confirmar. En el runner es un archivo JSONL en el workspace del job: si el job
 * muere, el paso final «reenviar pendientes» lo lee y los reenvía con SU clave (no se duplican).
 */
final class BandejaPendientes
{
    public function __construct(private readonly string $archivo)
    {
    }

    /** @param array{ruta: string, clave: string, cuerpo: array<string, mixed>} $pedido */
    public function guardar(array $pedido): void
    {
        file_put_contents($this->archivo, json_encode($pedido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }

    /** @return list<array{ruta: string, clave: string, cuerpo: array<string, mixed>}> */
    public function todos(): array
    {
        if (!is_file($this->archivo)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (string $l): mixed => json_decode($l, true),
            file($this->archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []), 'is_array'));
    }

    /** @param list<array{ruta: string, clave: string, cuerpo: array<string, mixed>}> $quedan */
    public function reemplazar(array $quedan): void
    {
        if ($quedan === []) {
            @unlink($this->archivo);

            return;
        }
        file_put_contents($this->archivo, implode('', array_map(static fn (array $p): string => json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", $quedan)), LOCK_EX);
    }
}
