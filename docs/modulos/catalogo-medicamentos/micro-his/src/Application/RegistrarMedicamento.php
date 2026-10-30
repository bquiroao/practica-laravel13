<?php
declare(strict_types=1);

namespace Catalogo\Application;

use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Modelo\Fecha;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Domain\Modelo\Vigencia;
use Catalogo\Domain\Puertos\GeneradorId;
use Catalogo\Domain\Puertos\Reloj;
use Catalogo\Domain\Repositorio\ControlEquivalencia;
use Catalogo\Domain\Repositorio\EscrituraCatalogo;

/** CU-01 Registrar medicamento (RF-01, RF-02, RF-03). */
final class RegistrarMedicamento
{
    public function __construct(
        private readonly ControlEquivalencia $equivalencia,
        private readonly EscrituraCatalogo $escritura,
        private readonly GeneradorId $ids,
        private readonly Reloj $reloj,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(string $hospitalId, array $datos, string $usuario): Medicamento
    {
        [$ficha, $desde] = LecturaDatos::fichaYFecha($datos, 'vigente_desde', Fecha::dia($this->reloj->hoy()));

        $existente = $this->equivalencia->equivalenteVigente($hospitalId, $ficha->clave(), new Vigencia($desde));
        if ($existente !== null) {
            throw new ErrorReglaNegocio('DUPLICADO', sprintf(
                'Ya existe %s con el mismo principio activo, concentración, forma y vía vigente en esas fechas',
                $existente->valor,
            ), ['codigo_existente' => $existente->valor]);
        }

        $nuevo = Medicamento::registrar($this->ids->nuevo(), $hospitalId, $this->escritura->siguienteCodigo($hospitalId), $ficha, $desde, $usuario);
        $this->escritura->agregar($nuevo);
        return $nuevo;
    }
}
