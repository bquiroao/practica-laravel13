<?php
declare(strict_types=1);

namespace MicroHis\Catalogo\Persistencia;

use MicroHis\Catalogo\Aplicacion\RepositorioCatalogo;

/** Adaptador en memoria del puerto RepositorioCatalogo: cumple el mismo contrato que RepositorioPdo, sin base. Para pruebas y demos. */
final class RepositorioEnMemoria implements RepositorioCatalogo
{
    /** @var array<string, array{nombre: string, activa: bool}> */
    private array $salas = [];
    /** @var array<string, string> "sala/cama" => estado */
    private array $camas = [];
    /** @var list<array{sala: string, cama: string, de: string, a: string, motivo: string}> */
    private array $historial = [];

    public function salaActiva(string $sala): ?bool { return $this->salas[$sala]['activa'] ?? null; }
    public function guardarSala(string $sala, string $nombre): void { $this->salas[$sala] = ['nombre' => $nombre, 'activa' => true]; }
    public function estadoCama(string $sala, string $cama): ?string { return $this->camas["$sala/$cama"] ?? null; }
    public function guardarCama(string $sala, string $cama): void { $this->camas["$sala/$cama"] = 'available'; }

    public function cambiarEstado(string $sala, string $cama, string $de, string $a, string $motivo): bool
    {
        if (($this->camas["$sala/$cama"] ?? null) !== $de) return false;   // concurrencia optimista, igual que el UPDATE ... WHERE status = ?
        $this->camas["$sala/$cama"] = $a;
        $this->historial[] = compact('sala', 'cama', 'de', 'a', 'motivo');
        return true;
    }

    /** Historial de cambios (R-09); en PDO es la tabla bed_status_history. */
    public function historial(): array { return $this->historial; }
}
