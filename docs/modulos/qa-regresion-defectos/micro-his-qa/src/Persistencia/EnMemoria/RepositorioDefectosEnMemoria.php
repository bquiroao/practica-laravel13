<?php

declare(strict_types=1);

namespace Qa\Persistencia\EnMemoria;

use Qa\Dominio\Modelo\Defecto;
use Qa\Dominio\Modelo\EstadoDefecto;
use Qa\Dominio\Modelo\Severidad;
use Qa\Dominio\Puertos\RepositorioDefectos;

/** Defectos e historial en memoria. Copias serializadas, sin el historial pendiente (que ya se agregó). */
final class RepositorioDefectosEnMemoria implements RepositorioDefectos, Restaurable
{
    /** @var array<int, string> */
    private array $filas = [];
    /** @var list<array{id: int, evento: string}> */
    private array $historial = [];
    private int $siguiente = 1;

    public function crear(Defecto $defecto): Defecto
    {
        $creado = $defecto->conId($this->siguiente++);
        $this->guardarFila($creado);
        $this->historial[] = ['id' => (int) $creado->id, 'evento' => "REPORTADO {$defecto->propuesta->value} por {$defecto->reportadoPor}"];
        foreach ($defecto->historialNuevo() as $e) {
            $this->historial[] = ['id' => (int) $creado->id, 'evento' => $e];
        }

        return $creado;
    }

    public function actualizar(Defecto $defecto): void
    {
        $this->guardarFila($defecto);
        foreach ($defecto->historialNuevo() as $e) {
            $this->historial[] = ['id' => (int) $defecto->id, 'evento' => $e];
        }
    }

    public function porId(string $tenant, int $id): ?Defecto
    {
        $d = isset($this->filas[$id]) ? unserialize($this->filas[$id]) : null;

        return $d !== null && $d->tenant === $tenant ? $d : null;
    }

    public function deCampana(string $tenant, int $campanaId): array
    {
        return array_values(array_filter(array_map('unserialize', $this->filas), static fn (Defecto $d): bool => $d->tenant === $tenant && $d->campanaId === $campanaId));
    }

    public function criticosAbiertos(string $tenant, array $casos): int
    {
        return count(array_filter(array_map('unserialize', $this->filas), static fn (Defecto $d): bool => $d->tenant === $tenant
            && in_array($d->casoId, $casos, true) && $d->criticoAbierto()));
    }

    public function mayoresAbiertos(string $tenant, array $casos): int
    {
        return count(array_filter(array_map('unserialize', $this->filas), static fn (Defecto $d): bool => $d->tenant === $tenant
            && in_array($d->casoId, $casos, true) && $d->severidad() === Severidad::MAYOR && $d->estado() !== EstadoDefecto::CERRADO));
    }

    /** @return list<string> eventos del historial de un defecto, en orden */
    public function historialDe(int $id): array
    {
        return array_values(array_column(array_filter($this->historial, static fn (array $h): bool => $h['id'] === $id), 'evento'));
    }

    private function guardarFila(Defecto $d): void
    {
        $this->filas[(int) $d->id] = serialize(new Defecto($d->id, $d->tenant, $d->campanaId, $d->casoId, $d->titulo, $d->pasos, $d->propuesta,
            $d->conEvidencia, $d->reportadoPor, $d->estado(), $d->severidad(), $d->prioridad()));
    }

    public function instantanea(): mixed
    {
        return [$this->filas, $this->historial, $this->siguiente];
    }

    public function restaurar(mixed $estado): void
    {
        [$this->filas, $this->historial, $this->siguiente] = $estado;
    }
}
