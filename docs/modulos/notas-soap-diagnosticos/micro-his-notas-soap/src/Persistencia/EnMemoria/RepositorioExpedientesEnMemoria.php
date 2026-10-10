<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\EnMemoria;

use NotasSoap\Dominio\Puertos\RepositorioExpedientes;

final class RepositorioExpedientesEnMemoria implements RepositorioExpedientes
{
    /**
     * @param array<string, array{paciente: string, abierto: bool}> $expedientes
     * @param array<string, string> $episodios episodio => paciente
     */
    public function __construct(private readonly array $expedientes, private readonly array $episodios)
    {
    }

    public function estaDisponible(string $expedienteId, string $episodioId): bool
    {
        $e = $this->expedientes[$expedienteId] ?? null;

        return $e !== null && $e['abierto'] && ($this->episodios[$episodioId] ?? null) === $e['paciente'];
    }
}
