<?php
declare(strict_types=1);

namespace Catalogo\Application;

use Catalogo\Domain\Errores\ErrorNoEncontrado;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Domain\Repositorio\HistorialCatalogo;

/** CU-05 Consultar historial de versiones (RF-07). */
final class ConsultarHistorial
{
    public function __construct(private readonly HistorialCatalogo $historial)
    {
    }

    /** @return list<Medicamento> */
    public function ejecutar(string $hospitalId, mixed $codigo): array
    {
        $c = LecturaDatos::codigo($codigo);
        $versiones = $this->historial->historial($hospitalId, $c);
        return $versiones !== [] ? $versiones : throw new ErrorNoEncontrado($c->valor);
    }
}
