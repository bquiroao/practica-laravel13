<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\EnMemoria;

use Prescripciones\Aplicacion\Puertos\RegistroAuditoria;

/** Bandeja de trazas en memoria (equivale a prescription_audit_outbox). */
final class RegistroAuditoriaEnMemoria implements RegistroAuditoria, Restaurable
{
    /** @var list<array{accion: string, hospital: string, usuario: string, recurso: string, resultado: string}> */
    private array $movimientos = [];

    public function registrar(string $accion, string $hospital, string $usuario, string $recurso, string $resultado): void
    {
        $this->movimientos[] = compact('accion', 'hospital', 'usuario', 'recurso', 'resultado');
    }

    /** @return list<array{accion: string, hospital: string, usuario: string, recurso: string, resultado: string}> */
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
