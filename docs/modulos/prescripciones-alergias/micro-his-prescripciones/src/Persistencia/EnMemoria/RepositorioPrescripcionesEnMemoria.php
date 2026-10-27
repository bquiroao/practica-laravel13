<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\EnMemoria;

use Prescripciones\Dominio\Excepciones\ClaveEnUso;
use Prescripciones\Dominio\Modelo\Prescripcion;
use Prescripciones\Dominio\Puertos\RepositorioPrescripciones;

/**
 * Adaptador InMemory del Repository. Reproduce lo que en PostgreSQL hacen las restricciones:
 * ids crecientes, UNIQUE (hospital, idempotency_key) → ClaveEnUso, y búsquedas acotadas al hospital.
 * La suite de contrato (tests/Contratos) verifica que se comporta igual que PdoRepositorioPrescripciones.
 */
final class RepositorioPrescripcionesEnMemoria implements RepositorioPrescripciones, Restaurable
{
    /** @var array<int, Prescripcion> */
    private array $porId = [];
    private int $ultimoId = 0;

    public function porClave(string $hospital, string $clave): ?Prescripcion
    {
        foreach ($this->porId as $p) {
            if ($p->hospital === $hospital && $p->clave === $clave) {
                return $p;
            }
        }

        return null;
    }

    public function porId(string $hospital, int $id): ?Prescripcion
    {
        $p = $this->porId[$id] ?? null;

        return $p !== null && $p->hospital === $hospital ? $p : null;
    }

    public function guardar(Prescripcion $prescripcion): Prescripcion
    {
        if ($this->porClave($prescripcion->hospital, $prescripcion->clave) !== null) {
            throw new ClaveEnUso('La clave de idempotencia ya se usó en este hospital.');
        }
        $guardada = $prescripcion->conId(++$this->ultimoId);
        $this->porId[$guardada->id] = $guardada;

        return $guardada;
    }

    /** @return list<Prescripcion> todas, en orden de id (para la demo y las pruebas) */
    public function todas(): array
    {
        return array_values($this->porId);
    }

    public function instantanea(): mixed
    {
        return $this->porId;
    }

    /** Como PostgreSQL: un ROLLBACK no devuelve los ids de la secuencia. */
    public function restaurar(mixed $estado): void
    {
        $this->porId = $estado;
    }
}
