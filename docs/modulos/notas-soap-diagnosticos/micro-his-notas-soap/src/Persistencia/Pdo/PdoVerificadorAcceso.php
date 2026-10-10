<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\Pdo;

use NotasSoap\Dominio\Modelo\UsuarioClinico;
use NotasSoap\Dominio\Puertos\VerificadorAcceso;

/** R-02: rol médico (del token) y asignación activa en care_assignments. */
final class PdoVerificadorAcceso extends RepositorioPdo implements VerificadorAcceso
{
    public function puedeEscribir(UsuarioClinico $usuario, string $episodioId): bool
    {
        if (!$usuario->tieneRol('medico')) {
            return false;
        }

        return $this->ejecutar(
            "SELECT 1
               FROM care_assignments
              WHERE care_episode_id = :episodio
                AND doctor_user_ref_id = :usuario
                AND status = 'active'",
            ['episodio' => $episodioId, 'usuario' => $usuario->id],
        )->fetchColumn() !== false;
    }
}
