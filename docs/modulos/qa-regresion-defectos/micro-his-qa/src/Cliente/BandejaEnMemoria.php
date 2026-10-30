<?php

declare(strict_types=1);

namespace Qa\Cliente;

final class BandejaEnMemoria implements BandejaPendientes
{
    /** @var array<string, array{ruta: string, cuerpo: array<string, mixed>}> */
    private array $pendientes = [];

    public function guardar(string $clave, string $ruta, array $cuerpo): void
    {
        $this->pendientes[$clave] = ['ruta' => $ruta, 'cuerpo' => $cuerpo];
    }

    public function quitar(string $clave): void
    {
        unset($this->pendientes[$clave]);
    }

    public function todos(): array
    {
        return $this->pendientes;
    }
}
