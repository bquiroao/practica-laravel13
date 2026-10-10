<?php

declare(strict_types=1);

namespace NotasSoap\Contratos;

use NotasSoap\Dominio\UsuarioAutenticado;

/** R-01 y R-02: tenant, rol médico y asignación activa al episodio (care_assignments). */
interface VerificadorAcceso
{
    public function puedeEscribir(UsuarioAutenticado $usuario, string $tenantSolicitud, string $episodioId): bool;
}
