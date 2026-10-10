<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Puertos;

use NotasSoap\Dominio\Modelo\UsuarioClinico;

/** R-02: rol médico y asignación activa al episodio (care_assignments). */
interface VerificadorAcceso
{
    public function puedeEscribir(UsuarioClinico $usuario, string $episodioId): bool;
}
