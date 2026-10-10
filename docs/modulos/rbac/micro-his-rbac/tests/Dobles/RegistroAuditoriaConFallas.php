<?php

declare(strict_types=1);

namespace Rbac\Pruebas\Dobles;

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Puertos\RegistroAuditoria;
use Rbac\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;

/** Decorador de prueba: bitácora InMemory real que puede «caerse» a pedido. */
final class RegistroAuditoriaConFallas implements RegistroAuditoria
{
    public bool $fallar = false;

    public function __construct(private readonly RegistroAuditoriaEnMemoria $interno)
    {
    }

    public function registrar(EventoAuditoria $evento): void
    {
        if ($this->fallar) {
            throw new ErrorPersistencia('Bitácora simulada no disponible', '08006');
        }
        $this->interno->registrar($evento);
    }

    public function buscarPorCorrelacion(string $correlationId): ?EventoAuditoria
    {
        return $this->interno->buscarPorCorrelacion($correlationId);
    }

    /** @return list<EventoAuditoria> */
    public function eventos(): array
    {
        return $this->interno->eventos();
    }

    /** @return list<EventoAuditoria> */
    public function eventosDeTipo(string $tipo): array
    {
        return array_values(array_filter($this->interno->eventos(), static fn (EventoAuditoria $e): bool => $e->tipo === $tipo));
    }
}
