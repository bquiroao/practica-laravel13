<?php

declare(strict_types=1);

namespace E2e\Persistencia\EnMemoria;

use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Dominio\Modelo\Ejecucion;
use E2e\Dominio\Modelo\ResultadoEscenario;
use E2e\Dominio\Puertos\RepositorioEjecuciones;

final class RepositorioEjecucionesEnMemoria implements RepositorioEjecuciones
{
    /** @var array<int, array{tenant: string, cabecera: string, resultados: list<string>}> */
    private array $filas = [];
    private int $siguiente = 1;
    private int $siguienteResultado = 1;

    public function crear(Ejecucion $ejecucion): Ejecucion
    {
        $e = $ejecucion->conId($this->siguiente++);
        $this->filas[(int) $e->id] = ['tenant' => $e->tenant, 'cabecera' => serialize(self::sinResultados($e)), 'resultados' => []];

        return $e;
    }

    public function actualizarEstado(Ejecucion $ejecucion): void
    {
        $this->filas[(int) $ejecucion->id]['cabecera'] = serialize(self::sinResultados($ejecucion));
    }

    public function agregarResultado(string $tenant, int $ejecucion, ResultadoEscenario $resultado): ResultadoEscenario
    {
        foreach ($this->filas as $f) {
            foreach ($f['resultados'] as $r) {
                if ($f['tenant'] === $tenant && unserialize($r)->clave === $resultado->clave) {
                    throw new ReglaRechazada('CLAVE_REUTILIZADA');                      // como el UNIQUE de la base
                }
            }
        }
        $r = $resultado->conId($this->siguienteResultado++);
        $this->filas[$ejecucion]['resultados'][] = serialize($r);

        return $r;
    }

    public function porId(string $tenant, int $id): ?Ejecucion
    {
        $f = $this->filas[$id] ?? null;
        if ($f === null || $f['tenant'] !== $tenant) {
            return null;
        }
        $c = unserialize($f['cabecera']);

        return new Ejecucion($c->id, $c->tenant, $c->version, $c->digest, $c->entorno, $c->plan, $c->iniciadaEn, $c->estado(), $c->terminadaEn(),
            $c->motivo(), array_map('unserialize', $f['resultados']));
    }

    public function resultadoPorClave(string $tenant, string $clave): ?array
    {
        foreach ($this->filas as $id => $f) {
            foreach ($f['resultados'] as $r) {
                $r = unserialize($r);
                if ($f['tenant'] === $tenant && $r->clave === $clave) {
                    return [$id, $r];
                }
            }
        }

        return null;
    }

    public function deVersion(string $tenant, string $version): array
    {
        $r = [];
        foreach ($this->filas as $id => $f) {
            $e = $this->porId($tenant, $id);
            if ($e !== null && $e->version === $version) {
                $r[] = $e;
            }
        }

        return $r;
    }

    private static function sinResultados(Ejecucion $e): Ejecucion
    {
        return new Ejecucion($e->id, $e->tenant, $e->version, $e->digest, $e->entorno, $e->plan, $e->iniciadaEn, $e->estado(), $e->terminadaEn(), $e->motivo());
    }

    public function instantanea(): mixed
    {
        return [$this->filas, $this->siguiente, $this->siguienteResultado];
    }

    public function restaurar(mixed $estado): void
    {
        [$this->filas, $this->siguiente, $this->siguienteResultado] = $estado;
    }
}
