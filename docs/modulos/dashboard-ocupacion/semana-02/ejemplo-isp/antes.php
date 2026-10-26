<?php
declare(strict_types=1);

// ANTES: el tablero usa el mismo almacén «gordo» que el catálogo. Depende de 6 métodos,
// y entre ellos hay uno de escritura de camas: nada impide que el tablero cambie estados (rompe R-09).
interface AlmacenOcupacionAntes
{
    public function salasActivas(): array;
    public function camas(string $sala): array;
    public function estanciasAbiertas(): array;
    public function guardarCorte(array $corte): void;
    public function ultimoCorte(): ?array;
    public function cambiarEstadoCama(string $sala, string $cama, string $estado): void;
}

/** La consolidación usa 4 métodos y la consulta 1, pero los dos dependen de los 6. */
final class ConsolidadorAntes
{
    public function __construct(private AlmacenOcupacionAntes $almacen) {}
}

final class ConsultaOcupacionAntes
{
    public function __construct(private AlmacenOcupacionAntes $almacen) {}
}
