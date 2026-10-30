<?php

declare(strict_types=1);

namespace E2e\Persistencia\EnMemoria;

use E2e\Aplicacion\Puertos\RegistroAuditoria;

final class RegistroAuditoriaEnMemoria implements RegistroAuditoria
{
    /** @var list<array{accion: string, tenant: string, usuario: string, recurso: string}> */
    private array $movimientos = [];

    public function registrar(string $accion, string $tenant, string $usuario, string $recurso): void
    {
        $this->movimientos[] = compact('accion', 'tenant', 'usuario', 'recurso');
    }

    /** @return list<string> */
    public function acciones(): array
    {
        return array_column($this->movimientos, 'accion');
    }

    public function instantanea(): mixed
    {
        return $this->movimientos;
    }

    public function restaurar(mixed $estado): void
    {
        $this->movimientos = $estado;
    }
}
