<?php
declare(strict_types=1);

namespace Catalogo\Domain\Repositorio;

use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\Medicamento;

/** ISP: cambios. Cada método es atómico: se aplica completo o no se aplica. */
interface EscrituraCatalogo
{
    public function siguienteCodigo(string $hospitalId): CodigoMedicamento;

    /** Alta de la versión 1. */
    public function agregar(Medicamento $nuevo): void;

    /** RN-01: cierra la versión anterior y agrega la nueva en una sola transacción. */
    public function reemplazarVigente(Medicamento $anteriorCerrada, Medicamento $nueva): void;

    /** RN-02: baja = guardar la versión con su vigencia cerrada. */
    public function cerrar(Medicamento $cerrada): void;

    /** Borra todas las versiones de un código. El caso de uso verifica antes que nunca se usó. */
    public function eliminar(string $hospitalId, CodigoMedicamento $codigo): void;
}
