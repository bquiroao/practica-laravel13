<?php

declare(strict_types=1);

namespace Auditoria\Persistencia\EnMemoria;

use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Modelo\ConsultaMovimientos;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Puertos\Bitacora;

/**
 * Semana 4 — Adaptador InMemory DE PRODUCCIÓN del Repository Bitacora (modo AUDITORIA_PERSISTENCIA=memoria).
 * Cumple el mismo contrato que PdoBitacora: lo verifica la suite tests/Contratos, que corre igual para los dos.
 * Reproduce las restricciones de schema.sql: (emisor, event_id) y (cadena, secuencia) únicos.
 */
final class BitacoraEnMemoria implements Bitacora, Restaurable
{
    /** @var array<int, Movimiento> id => movimiento */
    private array $movimientos = [];

    public function agregar(Movimiento $movimiento): Movimiento
    {
        foreach ($this->movimientos as $m) {
            if (($m->emisor === $movimiento->emisor && $m->eventId === $movimiento->eventId)
                || ($m->hospital === $movimiento->hospital && $m->secuencia === $movimiento->secuencia)) {
                throw new ErrorPersistencia('La bitácora no está disponible.', '23505');   // igual que la violación de unicidad
            }
        }
        $guardado = $movimiento->conId(count($this->movimientos) + 1);

        return $this->movimientos[$guardado->id] = $guardado;
    }

    public function bloquearCadena(?string $hospital): void
    {
        // Un solo proceso PHP: no hay concurrencia que serializar.
    }

    public function porEvento(string $emisor, string $eventId): ?Movimiento
    {
        foreach ($this->movimientos as $m) {
            if ($m->emisor === $emisor && $m->eventId === $eventId) {
                return $m;
            }
        }

        return null;
    }

    public function porId(int $id): ?Movimiento
    {
        return $this->movimientos[$id] ?? null;
    }

    public function ultimo(?string $hospital): ?Movimiento
    {
        $cadena = $this->cadena($hospital);

        return $cadena === [] ? null : $cadena[count($cadena) - 1];
    }

    public function buscar(ConsultaMovimientos $consulta): array
    {
        $encontrados = array_values(array_filter($this->movimientos, $consulta->coincide(...)));
        usort($encontrados, static fn (Movimiento $a, Movimiento $b): int => [$b->ocurridoEn, $b->id] <=> [$a->ocurridoEn, $a->id]);

        return array_slice($encontrados, 0, $consulta->limite);
    }

    public function cadena(?string $hospital): array
    {
        $cadena = array_values(array_filter($this->movimientos, static fn (Movimiento $m): bool => $m->hospital === $hospital));
        usort($cadena, static fn (Movimiento $a, Movimiento $b): int => $a->secuencia <=> $b->secuencia);

        return $cadena;
    }

    /** @return list<Movimiento> toda la bitácora, en orden de alta (para la demo y las pruebas). */
    public function todos(): array
    {
        return array_values($this->movimientos);
    }

    public function instantanea(): mixed
    {
        return $this->movimientos;
    }

    public function restaurar(mixed $instantanea): void
    {
        $this->movimientos = $instantanea;
    }
}
