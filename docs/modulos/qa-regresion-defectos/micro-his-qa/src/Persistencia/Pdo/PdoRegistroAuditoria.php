<?php

declare(strict_types=1);

namespace Qa\Persistencia\Pdo;

use Qa\Aplicacion\Puertos\RegistroAuditoria;

/**
 * Bandeja local (outbox): la traza se escribe en la MISMA transacción que el resultado o el defecto, y un proceso aparte
 * la envía al módulo Auditoría. No puede existir un intento sin su traza, ni al revés.
 */
final class PdoRegistroAuditoria extends ConsultasPdo implements RegistroAuditoria
{
    public function registrar(string $accion, string $tenant, string $usuario, string $recurso): void
    {
        $this->consulta('INSERT INTO auditoria_bandeja (accion, tenant, actor, recurso) VALUES (?, ?, ?, ?)', [$accion, $tenant, $usuario, $recurso]);
    }
}
