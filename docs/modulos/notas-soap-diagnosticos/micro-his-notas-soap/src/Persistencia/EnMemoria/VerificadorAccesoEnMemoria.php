<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\EnMemoria;

use NotasSoap\Dominio\Modelo\UsuarioClinico;
use NotasSoap\Dominio\Puertos\VerificadorAcceso;

final class VerificadorAccesoEnMemoria implements VerificadorAcceso
{
    /** @param array<string, list<string>> $asignaciones episodio => médicos con asignación activa */
    public function __construct(private readonly array $asignaciones)
    {
    }

    public function puedeEscribir(UsuarioClinico $usuario, string $episodioId): bool
    {
        return $usuario->tieneRol('medico') && in_array($usuario->id, $this->asignaciones[$episodioId] ?? [], true);
    }
}
