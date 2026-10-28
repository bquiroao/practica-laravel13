<?php
declare(strict_types=1);

// ANTES: una sola clase con cuatro razones para cambiar: reglas de estado,
// almacenamiento, formato de auditoría y respuesta HTTP.
final class CatalogoCamasAntes
{
    private array $salas = [];
    private array $camas = [];
    public array $bitacora = [];

    public function registrarSala(string $sala): array
    {
        if (trim($sala) === '') return ['status' => 422, 'error' => 'E2'];
        if (isset($this->salas[$sala])) return ['status' => 409, 'error' => 'E3'];
        $this->salas[$sala] = true;
        $this->bitacora[] = "alta sala $sala";
        return ['status' => 201, 'error' => null];
    }

    public function registrarCama(string $sala, string $cama): array
    {
        if (!($this->salas[$sala] ?? false)) return ['status' => 422, 'error' => 'E4'];
        if (trim($cama) === '') return ['status' => 422, 'error' => 'E2'];
        if (isset($this->camas["$sala/$cama"])) return ['status' => 409, 'error' => 'E3'];
        $this->camas["$sala/$cama"] = 'available';
        $this->bitacora[] = "alta cama $sala/$cama";
        return ['status' => 201, 'error' => null];
    }

    public function ocuparPorAdmision(string $sala, string $cama): void
    {
        $this->camas["$sala/$cama"] = 'occupied';
    }

    public function cambiarEstado(string $sala, string $cama, string $nuevo, string $motivo): array
    {
        $actual = $this->camas["$sala/$cama"] ?? null;
        if ($actual === null) return ['status' => 404, 'error' => 'NF'];
        if (trim($motivo) === '') return ['status' => 422, 'error' => 'E2'];
        if ($actual === 'occupied') return ['status' => 409, 'error' => 'E5'];
        $permitidas = ['available' => ['maintenance', 'inactive'], 'maintenance' => ['available', 'inactive'], 'inactive' => ['available']];
        if (!in_array($nuevo, $permitidas[$actual], true)) return ['status' => 422, 'error' => 'E6'];
        $this->camas["$sala/$cama"] = $nuevo;
        $this->bitacora[] = "estado $sala/$cama $actual->$nuevo: $motivo";
        return ['status' => 200, 'error' => null];
    }
}
