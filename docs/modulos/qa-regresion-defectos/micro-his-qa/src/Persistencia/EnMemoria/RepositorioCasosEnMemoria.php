<?php

declare(strict_types=1);

namespace Qa\Persistencia\EnMemoria;

use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\CasoPrueba;
use Qa\Dominio\Puertos\RepositorioCasos;

/** Casos versionados y matriz de regresión en memoria. Mismo contrato que PdoRepositorioCasos (tests/Contratos). */
final class RepositorioCasosEnMemoria implements RepositorioCasos, Restaurable
{
    /** @var array<string, CasoPrueba> "tenant|id|version" */
    private array $casos = [];
    /** @var array<string, array<string, bool>> "tenant|funcionalidad" => [caso => crítico] */
    private array $matriz = [];

    /** Carga de la matriz (lo que en PostgreSQL hace datos-demo.sql). */
    public function marcarEnMatriz(string $tenant, string $funcionalidad, string $caso, bool $critico): void
    {
        $this->matriz["$tenant|$funcionalidad"][$caso] = $critico;
    }

    public function guardar(CasoPrueba $caso): void
    {
        $clave = "{$caso->tenant}|{$caso->id}|{$caso->version}";
        if (isset($this->casos[$clave])) {
            throw new ReglaRechazada('CASO_EXISTENTE');   // como la PK (tenant, caso, versión)
        }
        $this->casos[$clave] = $caso;
    }

    public function aprobar(CasoPrueba $caso): void
    {
        $clave = "{$caso->tenant}|{$caso->id}|{$caso->version}";
        if (($this->casos[$clave]->estado ?? null) !== CasoPrueba::BORRADOR) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_APROBAR');
        }
        $this->casos[$clave] = $caso;
    }

    public function porId(string $tenant, string $id): ?CasoPrueba
    {
        return $this->variosPorId($tenant, [$id])[$id] ?? null;
    }

    public function variosPorId(string $tenant, array $ids): array
    {
        $r = [];
        foreach ($this->casos as $c) {
            if ($c->tenant === $tenant && in_array($c->id, $ids, true) && (!isset($r[$c->id]) || $r[$c->id]->version < $c->version)) {
                $r[$c->id] = $c;
            }
        }

        return $r;
    }

    public function criticosDe(string $tenant, array $funcionalidades): array
    {
        $r = [];
        foreach ($funcionalidades as $f) {
            foreach ($this->matriz["$tenant|$f"] ?? [] as $caso => $critico) {
                if ($critico) {
                    $r[$caso] = true;
                }
            }
        }
        $r = array_map('strval', array_keys($r));
        sort($r);

        return $r;
    }

    public function queCubren(string $tenant, string $requisito): array
    {
        $r = array_values(array_filter($this->casos, static fn (CasoPrueba $c): bool => $c->tenant === $tenant && in_array($requisito, $c->requisitos, true)));
        usort($r, static fn (CasoPrueba $a, CasoPrueba $b): int => [$a->id, $a->version] <=> [$b->id, $b->version]);

        return $r;
    }

    public function instantanea(): mixed
    {
        return $this->casos;
    }

    public function restaurar(mixed $estado): void
    {
        $this->casos = $estado;
    }
}
