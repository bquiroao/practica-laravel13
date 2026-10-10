<?php

declare(strict_types=1);

namespace NotasSoap\EnMemoria;

use NotasSoap\Contratos\VerificadorAcceso;
use NotasSoap\Dominio\UsuarioAutenticado;

final class VerificadorAccesoEnMemoria implements VerificadorAcceso
{
    /** @param array<string, list<string>> $asignaciones episodio => médicos asignados */
    public function __construct(private readonly array $asignaciones)
    {
    }

    public function puedeEscribir(UsuarioAutenticado $usuario, string $tenantSolicitud, string $episodioId): bool
    {
        return $usuario->tenantId === $tenantSolicitud
            && $usuario->tieneRol('medico')
            && in_array($usuario->id, $this->asignaciones[$episodioId] ?? [], true);
    }
}
