<?php

declare(strict_types=1);

namespace E2e\Persistencia\Pdo;

use E2e\Aplicacion\Puertos\RegistroAuditoria;

/** La traza va en la MISMA transacción que la operación: si una falla, ninguna queda. */
final class PdoRegistroAuditoria extends ConsultasPdo implements RegistroAuditoria
{
    public function registrar(string $accion, string $tenant, string $usuario, string $recurso): void
    {
        $this->consulta('INSERT INTO auditoria (tenant, usuario, accion, recurso) VALUES (?, ?, ?, ?)', [$tenant, $usuario, $accion, $recurso]);
    }
}
