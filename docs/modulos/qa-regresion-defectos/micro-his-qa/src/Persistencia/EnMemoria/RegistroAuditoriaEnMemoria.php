<?php

declare(strict_types=1);

namespace Qa\Persistencia\EnMemoria;

use Qa\Aplicacion\Puertos\RegistroAuditoria;

/** Bandeja de auditoría en memoria: participa en la transacción como la tabla auditoria_bandeja. */
final class RegistroAuditoriaEnMemoria implements RegistroAuditoria, Restaurable
{
    /** @var list<array{accion: string, tenant: string, usuario: string, recurso: string}> */
    private array $movimientos = [];

    public function registrar(string $accion, string $tenant, string $usuario, string $recurso): void
    {
        $this->movimientos[] = ['accion' => $accion, 'tenant' => $tenant, 'usuario' => $usuario, 'recurso' => $recurso];
    }

    /** @return list<array{accion: string, tenant: string, usuario: string, recurso: string}> */
    public function movimientos(): array
    {
        return $this->movimientos;
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
