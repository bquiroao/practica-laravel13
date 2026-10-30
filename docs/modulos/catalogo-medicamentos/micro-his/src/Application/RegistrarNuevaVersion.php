<?php
declare(strict_types=1);

namespace Catalogo\Application;

use Catalogo\Domain\Errores\ErrorNoEncontrado;
use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Modelo\Fecha;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Domain\Modelo\Vigencia;
use Catalogo\Domain\Puertos\GeneradorId;
use Catalogo\Domain\Puertos\Reloj;
use Catalogo\Domain\Repositorio\ControlEquivalencia;
use Catalogo\Domain\Repositorio\EscrituraCatalogo;
use Catalogo\Domain\Repositorio\HistorialCatalogo;

/** CU-03 Registrar nueva versión (RF-05, RN-01, RN-04). */
final class RegistrarNuevaVersion
{
    public function __construct(
        private readonly HistorialCatalogo $historial,
        private readonly ControlEquivalencia $equivalencia,
        private readonly EscrituraCatalogo $escritura,
        private readonly GeneradorId $ids,
        private readonly Reloj $reloj,
    ) {
    }

    /** @param array<string, mixed> $datos ficha completa de la nueva versión + vigente_desde */
    public function ejecutar(string $hospitalId, mixed $codigo, array $datos, string $usuario): Medicamento
    {
        $c = LecturaDatos::codigo($codigo);
        [$ficha, $desde] = LecturaDatos::fichaYFecha($datos, 'vigente_desde', Fecha::dia($this->reloj->hoy()));

        $versiones = $this->historial->historial($hospitalId, $c);
        if ($versiones === []) {
            throw new ErrorNoEncontrado($c->valor);
        }
        $ultima = $versiones[array_key_last($versiones)];
        [$cerrada, $nueva] = $ultima->nuevaVersion($this->ids->nuevo(), $ficha, $desde, $usuario);

        $otro = $this->equivalencia->equivalenteVigente($hospitalId, $nueva->clave(), $nueva->vigencia, $c);
        if ($otro !== null) {
            throw new ErrorReglaNegocio('DUPLICADO', sprintf(
                'La nueva versión sería equivalente a %s, vigente en esas fechas',
                $otro->valor,
            ), ['codigo_existente' => $otro->valor]);
        }

        $this->escritura->reemplazarVigente($cerrada, $nueva);
        return $nueva;
    }
}
