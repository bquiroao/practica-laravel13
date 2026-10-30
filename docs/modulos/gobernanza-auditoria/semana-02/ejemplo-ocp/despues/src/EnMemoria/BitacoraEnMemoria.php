<?php

declare(strict_types=1);

namespace Auditoria\EnMemoria;

use Auditoria\Contratos\Bitacora;
use Auditoria\Dominio\ConsultaMovimientos;
use Auditoria\Dominio\ErrorPersistencia;
use Auditoria\Dominio\Movimiento;

/** Bitácora en memoria. Permite simular fallas y, SOLO para pruebas, una alteración directa. */
final class BitacoraEnMemoria implements Bitacora
{
    /** @var array<int, Movimiento> */
    private array $movimientos = [];

    /** @var list<string> operaciones que lanzan ErrorPersistencia */
    public array $fallarEn = [];

    public function agregar(Movimiento $movimiento): void
    {
        $this->quizasFallar('agregar');
        $this->movimientos[$movimiento->id] = $movimiento;
    }

    public function porEvento(string $emisor, string $eventId): ?Movimiento
    {
        $this->quizasFallar('porEvento');
        foreach ($this->movimientos as $m) {
            if ($m->emisor === $emisor && $m->eventId === $eventId) {
                return $m;
            }
        }

        return null;
    }

    public function porId(int $id): ?Movimiento
    {
        $this->quizasFallar('porId');

        return $this->movimientos[$id] ?? null;
    }

    public function ultimo(?string $hospital): ?Movimiento
    {
        $this->quizasFallar('ultimo');
        $cadena = $this->cadena($hospital);

        return $cadena === [] ? null : $cadena[count($cadena) - 1];
    }

    public function siguienteId(): int
    {
        return count($this->movimientos) + 1;
    }

    public function buscar(ConsultaMovimientos $consulta): array
    {
        $this->quizasFallar('buscar');
        $encontrados = array_values(array_filter($this->movimientos, $consulta->coincide(...)));
        usort($encontrados, static fn (Movimiento $a, Movimiento $b): int
            => [$b->ocurridoEn->format('Y-m-d\TH:i:s\Z'), $b->id] <=> [$a->ocurridoEn->format('Y-m-d\TH:i:s\Z'), $a->id]);

        return array_slice($encontrados, 0, $consulta->limite);
    }

    public function cadena(?string $hospital): array
    {
        $cadena = array_values(array_filter($this->movimientos, static fn (Movimiento $m): bool => $m->hospital === $hospital));
        usort($cadena, static fn (Movimiento $a, Movimiento $b): int => $a->secuencia <=> $b->secuencia);

        return $cadena;
    }

    /** @return list<Movimiento> */
    public function todos(): array
    {
        return array_values($this->movimientos);
    }

    /** SOLO PRUEBAS: simula a alguien que modifica la fila directamente en la base, saltándose el trigger. */
    public function alterarParaPrueba(int $id, Movimiento $reemplazo): void
    {
        $this->movimientos[$id] = $reemplazo;
    }

    public function instantanea(): array
    {
        return $this->movimientos;
    }

    public function restaurar(array $instantanea): void
    {
        $this->movimientos = $instantanea;
    }

    private function quizasFallar(string $operacion): void
    {
        if (in_array($operacion, $this->fallarEn, true)) {
            throw new ErrorPersistencia("Simulado: base no disponible en $operacion");
        }
    }
}
