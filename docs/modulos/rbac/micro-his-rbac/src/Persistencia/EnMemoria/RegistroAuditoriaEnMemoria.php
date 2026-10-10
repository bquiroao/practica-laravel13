<?php

declare(strict_types=1);

namespace Rbac\Persistencia\EnMemoria;

use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Puertos\RegistroAuditoria;

/** Bitácora append-only en memoria: no ofrece ninguna forma de editar ni borrar eventos. */
final class RegistroAuditoriaEnMemoria implements RegistroAuditoria, Restaurable
{
    /** @var list<EventoAuditoria> */
    private array $eventos = [];

    public function registrar(EventoAuditoria $evento): void
    {
        $this->eventos[] = $evento;
    }

    public function buscarPorCorrelacion(string $correlationId): ?EventoAuditoria
    {
        foreach ($this->eventos as $evento) {
            if ($evento->correlationId === $correlationId) {
                return $evento;
            }
        }

        return null;
    }

    /** @return list<EventoAuditoria> */
    public function eventos(): array
    {
        return $this->eventos;
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
