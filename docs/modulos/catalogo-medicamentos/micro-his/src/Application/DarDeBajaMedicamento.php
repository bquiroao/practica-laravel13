<?php
declare(strict_types=1);

namespace Catalogo\Application;

use Catalogo\Domain\Errores\ErrorNoEncontrado;
use Catalogo\Domain\Modelo\Fecha;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Domain\Puertos\Reloj;
use Catalogo\Domain\Repositorio\EscrituraCatalogo;
use Catalogo\Domain\Repositorio\HistorialCatalogo;

/** CU-04 Dar de baja (RF-06, RN-02): cierra la vigencia de la última versión, no borra. */
final class DarDeBajaMedicamento
{
    public function __construct(
        private readonly HistorialCatalogo $historial,
        private readonly EscrituraCatalogo $escritura,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(string $hospitalId, mixed $codigo, mixed $fechaBaja, mixed $motivo): Medicamento
    {
        $c = LecturaDatos::codigo($codigo);
        $fecha = $fechaBaja === null || $fechaBaja === '' ? Fecha::dia($this->reloj->hoy()) : Fecha::desdeTexto('fecha_baja', $fechaBaja);
        $versiones = $this->historial->historial($hospitalId, $c);
        if ($versiones === []) {
            throw new ErrorNoEncontrado($c->valor);
        }
        $cerrada = $versiones[array_key_last($versiones)]->darDeBaja($fecha, $motivo);
        $this->escritura->cerrar($cerrada);
        return $cerrada;
    }
}
