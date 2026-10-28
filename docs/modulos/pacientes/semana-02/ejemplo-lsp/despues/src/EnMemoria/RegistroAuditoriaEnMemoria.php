<?php

declare(strict_types=1);

namespace Pacientes\EnMemoria;

use Pacientes\Contratos\RegistroAuditoria;
use Pacientes\Dominio\EventoAuditoria;

final class RegistroAuditoriaEnMemoria implements RegistroAuditoria, Restaurable
{
    /** @var list<EventoAuditoria> */
    private array $eventos = [];

    public function registrar(EventoAuditoria $evento): void
    {
        $this->eventos[] = $evento;
    }

    /** @return list<EventoAuditoria> */
    public function eventos(?string $accion = null): array
    {
        return array_values(array_filter($this->eventos, static fn (EventoAuditoria $e): bool => $accion === null || $e->accion === $accion));
    }

    public function instantanea(): mixed
    {
        return $this->eventos;
    }

    public function restaurar(mixed $instantanea): void
    {
        $this->eventos = $instantanea;
    }
}
