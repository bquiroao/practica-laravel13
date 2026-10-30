<?php
declare(strict_types=1);

namespace Catalogo\Application;

use Catalogo\Domain\Errores\ErrorNoEncontrado;
use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Puertos\ConsultaUsoPrescripciones;
use Catalogo\Domain\Repositorio\EscrituraCatalogo;
use Catalogo\Domain\Repositorio\HistorialCatalogo;

/** CU-06 Eliminar registro erróneo (RF-08, RN-02): solo si nunca se usó en prescripciones. */
final class EliminarRegistroSinUso
{
    public function __construct(
        private readonly HistorialCatalogo $historial,
        private readonly ConsultaUsoPrescripciones $uso,
        private readonly EscrituraCatalogo $escritura,
    ) {
    }

    public function ejecutar(string $hospitalId, mixed $codigo): void
    {
        $c = LecturaDatos::codigo($codigo);
        if ($this->historial->historial($hospitalId, $c) === []) {
            throw new ErrorNoEncontrado($c->valor);
        }
        if ($this->uso->fueUsado($hospitalId, $c)) {
            throw new ErrorReglaNegocio('EN_USO', sprintf(
                '%s ya se usó en prescripciones: no se puede eliminar, dé de baja su vigencia',
                $c->valor,
            ), ['accion_sugerida' => 'baja']);
        }
        $this->escritura->eliminar($hospitalId, $c);
    }
}
