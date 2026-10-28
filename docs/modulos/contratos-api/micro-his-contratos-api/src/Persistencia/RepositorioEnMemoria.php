<?php
declare(strict_types=1);

namespace MicroHis\ContratosApi\Persistencia;

use MicroHis\ContratosApi\Aplicacion\RepositorioDocumentos;
use MicroHis\ContratosApi\Dominio\VersionNueva;

/** Adaptador en memoria de la interfaz Repository: cumple el mismo contrato que RepositorioPdo, sin base. Para pruebas y demos. */
final class RepositorioEnMemoria implements RepositorioDocumentos
{
    /** @var array<int, array{slug: string, tipo: string, version: string, autor: string, estado: string, audiencia: string[], revisor: ?string, motivo: ?string}> */
    private array $versiones = [];

    public function versiones(string $slug): array
    {
        return array_values(array_map(fn (array $v) => $v['version'], array_filter($this->versiones, fn (array $v) => $v['slug'] === $slug && $v['estado'] !== 'rejected')));
    }
    public function guardarEnRevision(VersionNueva $v, string $autor): int
    {
        $this->versiones[count($this->versiones) + 1] = ['slug' => $v->slug, 'tipo' => $v->tipo, 'version' => $v->version, 'autor' => $autor,
                                                         'estado' => 'in_review', 'audiencia' => $v->audiencia, 'revisor' => null, 'motivo' => null];
        return count($this->versiones);
    }
    public function datosRevision(int $id): ?array
    {
        return isset($this->versiones[$id]) ? ['autor' => $this->versiones[$id]['autor'], 'estado' => $this->versiones[$id]['estado']] : null;
    }
    public function cambiarEstado(int $id, string $de, string $a, string $revisor, ?string $motivo): bool
    {
        if (($this->versiones[$id]['estado'] ?? null) !== $de) return false;   // concurrencia optimista, igual que el UPDATE ... WHERE status = ?
        $this->versiones[$id] = ['estado' => $a, 'revisor' => $revisor, 'motivo' => $motivo ?? $this->versiones[$id]['motivo']] + $this->versiones[$id];
        return true;
    }
    public function consultables(string $slug): array
    {
        $visibles = array_filter($this->versiones, fn (array $v) => $v['slug'] === $slug && in_array($v['estado'], ['published', 'obsolete'], true));
        return array_values(array_map(fn (array $v) => ['version' => $v['version'], 'tipo' => $v['tipo'], 'estado' => $v['estado'], 'audiencia' => $v['audiencia']], $visibles));
    }
}
