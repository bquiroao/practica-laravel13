<?php

declare(strict_types=1);

namespace NotasSoap\EnMemoria;

use NotasSoap\Contratos\RepositorioExpedientes;

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
        $expediente = $this->expedientes[$expedienteId] ?? null;

        return $expediente !== null
            && $expediente['abierto']
            && ($this->episodios[$episodioId] ?? null) === $expediente['paciente'];
    }
}
