<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Modelo;

/** Módulos y acciones que la bitácora acepta. Se acuerda con cada módulo emisor. */
final class CatalogoAcciones
{
    /** @param array<string, list<string>> $acciones módulo => acciones */
    public function __construct(private readonly array $acciones)
    {
    }

    public function admite(string $modulo, string $accion): bool
    {
        return in_array($accion, $this->acciones[$modulo] ?? [], true);
    }
}
