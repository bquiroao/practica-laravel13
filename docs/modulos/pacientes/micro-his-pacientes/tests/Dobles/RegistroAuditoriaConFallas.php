<?php

declare(strict_types=1);

namespace Pacientes\Pruebas\Dobles;

use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Dominio\Puertos\RegistroAuditoria;
use Pacientes\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;

/** Decorador: reutiliza la bitácora InMemory real y solo agrega «la bitácora se cayó». */
final class RegistroAuditoriaConFallas implements RegistroAuditoria
{
    public bool $fallar = false;

    public function __construct(public readonly RegistroAuditoriaEnMemoria $real = new RegistroAuditoriaEnMemoria())
    {
    }

    public function registrar(EventoAuditoria $evento): void
    {
        if ($this->fallar) {
            throw new ErrorPersistencia('Simulado: bitácora no disponible', '08006');
        }
        $this->real->registrar($evento);
    }

    /** @return list<EventoAuditoria> */
    public function eventos(?string $accion = null): array
    {
        return $this->real->eventos($accion);
    }
}
