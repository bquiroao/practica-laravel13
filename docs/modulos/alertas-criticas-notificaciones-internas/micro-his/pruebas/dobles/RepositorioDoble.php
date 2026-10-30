<?php
// Doble de prueba del puerto AlertaRepository (no es el adaptador InMemory de la semana 4):
// guarda en un arreglo y puede simular una falla de persistencia.
declare(strict_types=1);

use AlertasCriticas\Domain\{Alerta, AlertaRepository, ErrorPersistenciaException};

final class RepositorioDoble implements AlertaRepository
{
    /** @var array<string, Alerta> */
    public array $alertas = [];
    public int $guardados = 0;

    public function __construct(private bool $fallarAlGuardar = false) {}

    public function guardar(Alerta $alerta): void
    {
        if ($this->fallarAlGuardar) {
            throw new ErrorPersistenciaException('Simulación: la base de datos no responde');
        }
        $this->guardados++;
        $this->alertas[$alerta->id] = $alerta;
    }

    public function buscar(string $id, string $tenant): ?Alerta
    {
        $a = $this->alertas[$id] ?? null;
        return $a !== null && $a->tenant === $tenant ? $a : null;
    }

    public function buscarPorOrigen(string $tenant, string $origen, string $idOrigen): ?Alerta
    {
        foreach ($this->alertas as $a) {
            if ($a->tenant === $tenant && $a->origen === $origen && $a->idOrigen === $idOrigen) {
                return $a;
            }
        }
        return null;
    }

    public function pendientesDeAcuse(): array
    {
        return array_values(array_filter($this->alertas, fn (Alerta $a) => $a->estado()->esperaAcuse() && !$a->escalamientoAgotado()));
    }

    public function listar(string $tenant): array
    {
        return array_values(array_filter($this->alertas, fn (Alerta $a) => $a->tenant === $tenant));
    }
}
