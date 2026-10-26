<?php

declare(strict_types=1);

namespace Auditoria\Pruebas\Dobles;

use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Modelo\ConsultaMovimientos;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Puertos\Bitacora;

/**
 * Doble de la bitácora para las pruebas: mismo contrato que PdoBitacora, sin base de datos.
 * Simula fallas por operación y, SOLO aquí, una alteración directa de una fila.
 */
final class BitacoraEnMemoria implements Bitacora
{
    /** @var array<int, Movimiento> */
    private array $movimientos = [];

    /** @var list<string> operaciones que lanzan ErrorPersistencia */
    public array $fallarEn = [];

    /** @var list<?string> cadenas bloqueadas, para comprobar que el registro las bloquea */
    public array $bloqueos = [];

    public function agregar(Movimiento $movimiento): Movimiento
    {
        $this->quizasFallar('agregar');
        $guardado = $movimiento->conId(count($this->movimientos) + 1);

        return $this->movimientos[$guardado->id] = $guardado;
    }

    public function bloquearCadena(?string $hospital): void
    {
        $this->quizasFallar('bloquearCadena');
        $this->bloqueos[] = $hospital;
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

    public function buscar(ConsultaMovimientos $consulta): array
    {
        $this->quizasFallar('buscar');
        $encontrados = array_values(array_filter($this->movimientos, $consulta->coincide(...)));
        usort($encontrados, static fn (Movimiento $a, Movimiento $b): int => [$b->ocurridoEn, $b->id] <=> [$a->ocurridoEn, $a->id]);

        return array_slice($encontrados, 0, $consulta->limite);
    }

    public function cadena(?string $hospital): array
    {
        $this->quizasFallar('cadena');
        $cadena = array_values(array_filter($this->movimientos, static fn (Movimiento $m): bool => $m->hospital === $hospital));
        usort($cadena, static fn (Movimiento $a, Movimiento $b): int => $a->secuencia <=> $b->secuencia);

        return $cadena;
    }

    /** @return list<Movimiento> */
    public function todos(): array
    {
        return array_values($this->movimientos);
    }

    /** SOLO PRUEBAS: alguien modifica la fila en la base saltándose el trigger. */
    public function alterarParaPrueba(int $id, Movimiento $reemplazo): void
    {
        $this->movimientos[$id] = $reemplazo->conId($id);
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
            throw new ErrorPersistencia("Simulado: base no disponible en $operacion", '08006');
        }
    }
}
