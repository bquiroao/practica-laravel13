<?php

declare(strict_types=1);

namespace Auditoria\Pruebas\Dobles;

use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Modelo\ConsultaMovimientos;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Puertos\Bitacora;
use Auditoria\Persistencia\EnMemoria\BitacoraEnMemoria;

/**
 * Decorador de PRUEBA sobre el adaptador InMemory de producción: simula fallas por operación,
 * registra los candados pedidos y, SOLO aquí, una alteración directa de una fila (como quien
 * edita la base saltándose el trigger). El adaptador de producción no sabe nada de esto.
 */
final class BitacoraConFallas implements Bitacora
{
    /** @var list<string> operaciones que lanzan ErrorPersistencia */
    public array $fallarEn = [];

    /** @var list<?string> */
    public array $bloqueos = [];

    /** @var array<int, Movimiento> id => fila alterada */
    private array $alteradas = [];

    /** @var array<int, true> ids borrados «por fuera» */
    private array $ocultas = [];

    public function __construct(public readonly BitacoraEnMemoria $real = new BitacoraEnMemoria())
    {
    }

    public function agregar(Movimiento $movimiento): Movimiento
    {
        $this->quizasFallar('agregar');

        return $this->real->agregar($movimiento);
    }

    public function bloquearCadena(?string $hospital): void
    {
        $this->quizasFallar('bloquearCadena');
        $this->bloqueos[] = $hospital;
        $this->real->bloquearCadena($hospital);
    }

    public function porEvento(string $emisor, string $eventId): ?Movimiento
    {
        $this->quizasFallar('porEvento');

        return $this->alterada($this->real->porEvento($emisor, $eventId));
    }

    public function porId(int $id): ?Movimiento
    {
        $this->quizasFallar('porId');

        return $this->alterada($this->real->porId($id));
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

        return array_values(array_filter(array_map($this->alterada(...), $this->real->buscar($consulta))));
    }

    public function cadena(?string $hospital): array
    {
        $this->quizasFallar('cadena');

        return array_values(array_filter(array_map($this->alterada(...), $this->real->cadena($hospital))));
    }

    /** @return list<Movimiento> */
    public function todos(): array
    {
        return array_values(array_filter(array_map($this->alterada(...), $this->real->todos())));
    }

    /** SOLO PRUEBAS: a partir de ahora, la fila $id se lee como $reemplazo. */
    public function alterarParaPrueba(int $id, Movimiento $reemplazo): void
    {
        $this->alteradas[$id] = $reemplazo->conId($id);
    }

    /** SOLO PRUEBAS: la fila $id desaparece, como un DELETE hecho con el trigger desactivado. */
    public function ocultarParaPrueba(int $id): void
    {
        $this->ocultas[$id] = true;
    }

    private function alterada(?Movimiento $m): ?Movimiento
    {
        return $m === null || isset($this->ocultas[$m->id]) ? null : ($this->alteradas[$m->id] ?? $m);
    }

    private function quizasFallar(string $operacion): void
    {
        if (in_array($operacion, $this->fallarEn, true)) {
            throw new ErrorPersistencia("Simulado: base no disponible en $operacion", '08006');
        }
    }
}
