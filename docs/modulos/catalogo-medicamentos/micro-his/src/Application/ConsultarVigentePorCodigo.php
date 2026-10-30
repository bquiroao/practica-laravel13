<?php
declare(strict_types=1);

namespace Catalogo\Application;

use Catalogo\Domain\Errores\ErrorNoEncontrado;
use Catalogo\Domain\Errores\ErrorValidacion;
use Catalogo\Domain\Modelo\Fecha;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Domain\Puertos\Reloj;
use Catalogo\Domain\Repositorio\BusquedaCatalogo;

/** RF-09: versión vigente de un código a una fecha (lectura para otros módulos). */
final class ConsultarVigentePorCodigo
{
    public function __construct(private readonly BusquedaCatalogo $busqueda, private readonly Reloj $reloj)
    {
    }

    public function ejecutar(string $hospitalId, mixed $codigo, mixed $fecha = null): Medicamento
    {
        $c = LecturaDatos::codigo($codigo);
        $dia = $fecha === null || $fecha === '' ? Fecha::dia($this->reloj->hoy()) : Fecha::desdeTexto('fecha', $fecha);
        return $this->busqueda->vigentePorCodigo($hospitalId, $c, $dia) ?? throw new ErrorNoEncontrado($c->valor);
    }
}
