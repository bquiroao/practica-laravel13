<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Servicios;

use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Modelo\Sello;
use Auditoria\Dominio\Puertos\Bitacora;
use Auditoria\Dominio\Puertos\Reloj;

/**
 * La ÚNICA vía de escritura a la bitácora: bloquea la cadena del hospital, toma el último sello,
 * sella y agrega (R-03). La usan el registro (CU-01) y la meta-auditoría (CU-06), así que una
 * consulta queda sellada igual que cualquier otro movimiento. Debe llamarse dentro de una transacción.
 */
final class CadenaSellada
{
    /** @param \Closure(): string $nuevoEventId */
    public function __construct(
        private readonly Bitacora $bitacora,
        private readonly Reloj $reloj,
        private readonly \Closure $nuevoEventId,
    ) {
    }

    /** @throws ErrorPersistencia */
    public function anexar(Movimiento $movimiento): Movimiento
    {
        $this->bitacora->bloquearCadena($movimiento->hospital);
        $anterior = $this->bitacora->ultimo($movimiento->hospital);
        $secuencia = ($anterior?->secuencia ?? 0) + 1;

        return $this->bitacora->agregar($movimiento->sellado($secuencia,
            Sello::calcular($anterior?->sello ?? Sello::GENESIS, $secuencia, $movimiento)));
    }

    /**
     * R-08: deja constancia de una lectura de la bitácora.
     *
     * @throws ErrorPersistencia
     */
    public function auditarLectura(?string $hospital, string $actor, string $accion, array $detalle): void
    {
        $this->anexar(Movimiento::delSistema(($this->nuevoEventId)(), $hospital, $actor, $accion, $this->reloj->ahora(), $detalle));
    }
}
