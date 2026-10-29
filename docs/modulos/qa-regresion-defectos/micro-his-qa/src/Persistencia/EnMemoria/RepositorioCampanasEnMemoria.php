<?php

declare(strict_types=1);

namespace Qa\Persistencia\EnMemoria;

use Qa\Dominio\Excepciones\ClaveEnUso;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\Campana;
use Qa\Dominio\Modelo\Intento;
use Qa\Dominio\Puertos\RepositorioCampanas;

/**
 * Campañas e intentos en memoria. Guarda copias serializadas: quien recibe una campaña no puede cambiar lo guardado
 * modificando el objeto (como pasa con una fila de la base). Los intentos solo se agregan.
 */
final class RepositorioCampanasEnMemoria implements RepositorioCampanas, Restaurable
{
    private const ANTERIOR = ['EN_CURSO' => 'PLANIFICADA', 'CERRADA' => 'EN_CURSO'];

    /** @var array<int, string> campaña serializada, sin intentos */
    private array $filas = [];
    /** @var array<int, Intento> */
    private array $intentos = [];
    /** @var array<int, string> id de intento => tenant */
    private array $tenantDe = [];
    private int $siguiente = 1;
    private int $siguienteIntento = 1;

    public function crear(Campana $campana): Campana
    {
        $creada = $campana->conId($this->siguiente++);
        $this->filas[$creada->id] = serialize($creada);

        return $creada;
    }

    public function actualizarEstado(Campana $campana): void
    {
        $actual = isset($this->filas[(int) $campana->id]) ? unserialize($this->filas[(int) $campana->id]) : null;
        $estado = $campana->estado()->value;
        if ($actual === null || $actual->tenant !== $campana->tenant || $actual->estado()->value !== (self::ANTERIOR[$estado] ?? $estado)) {
            throw new ReglaRechazada('ESTADO_CAMBIO_CONCURRENTE');
        }
        $this->filas[$campana->id] = serialize(new Campana($campana->id, $campana->tenant, $campana->nombre, $campana->funcionalidades,
            $campana->alcance, $campana->umbral, $campana->creadaPor, $campana->estado(), $campana->build(), $campana->decision()));
    }

    public function porId(string $tenant, int $id): ?Campana
    {
        $c = isset($this->filas[$id]) ? unserialize($this->filas[$id]) : null;
        if ($c === null || $c->tenant !== $tenant) {
            return null;
        }
        $intentos = array_values(array_filter($this->intentos, static fn (Intento $i): bool => $i->campanaId === $id));

        return new Campana($c->id, $c->tenant, $c->nombre, $c->funcionalidades, $c->alcance, $c->umbral, $c->creadaPor, $c->estado(), $c->build(),
            $c->decision(), $intentos);
    }

    public function agregarIntento(string $tenant, Intento $intento): Intento
    {
        foreach ($this->intentos as $id => $i) {
            if ($this->tenantDe[$id] === $tenant && $i->clave === $intento->clave) {
                throw new ClaveEnUso('clave de idempotencia en uso');
            }
            if ($i->campanaId === $intento->campanaId && $i->casoId === $intento->casoId && $i->numero === $intento->numero) {
                throw new ReglaRechazada('INTENTO_CONCURRENTE');
            }
        }
        $con = $intento->conId($this->siguienteIntento++);
        $this->intentos[$con->id] = $con;
        $this->tenantDe[$con->id] = $tenant;

        return $con;
    }

    public function intentoPorClave(string $tenant, string $clave): ?Intento
    {
        foreach ($this->intentos as $id => $i) {
            if ($this->tenantDe[$id] === $tenant && $i->clave === $clave) {
                return $i;
            }
        }

        return null;
    }

    /** @return list<Intento> todos los intentos guardados (para inspección y demo) */
    public function todosLosIntentos(): array
    {
        return array_values($this->intentos);
    }

    public function instantanea(): mixed
    {
        return [$this->filas, $this->intentos, $this->tenantDe, $this->siguiente, $this->siguienteIntento];
    }

    public function restaurar(mixed $estado): void
    {
        [$this->filas, $this->intentos, $this->tenantDe, $this->siguiente, $this->siguienteIntento] = $estado;
    }
}
