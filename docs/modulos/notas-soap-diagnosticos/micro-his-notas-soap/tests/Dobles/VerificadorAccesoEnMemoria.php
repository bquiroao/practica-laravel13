<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Dominio\Modelo\UsuarioClinico;
use NotasSoap\Dominio\Puertos\VerificadorAcceso;

final class VerificadorAccesoEnMemoria implements VerificadorAcceso
{
    public bool $explotar = false;

    /** @param array<string, list<string>> $asignaciones */
    public function __construct(private readonly array $asignaciones)
    {
    }

    public function puedeEscribir(UsuarioClinico $usuario, string $episodioId): bool
    {
        if ($this->explotar) {
            throw new \LogicException('fallo inesperado con detalle interno: tabla care_assignments');
        }

        return $usuario->tieneRol('medico') && in_array($usuario->id, $this->asignaciones[$episodioId] ?? [], true);
    }
}
