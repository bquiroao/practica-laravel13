<?php
declare(strict_types=1);

namespace Admision\Application;

use Admision\Domain\Elegibilidad\EvaluadorElegibilidad;
use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\Requerimiento;
use Admision\Domain\Modelo\Rol;
use Admision\Domain\Repositorio\AdmisionRepository;
use Admision\Domain\Repositorio\CatalogoCamas;

/** CU-03: camas operativas del hospital, sin asignación activa y compatibles (A-07, A-08, A-09). */
final class ConsultarCamasDisponibles
{
    public function __construct(
        private readonly CatalogoCamas $catalogo,
        private readonly AdmisionRepository $admisiones,
        private readonly EvaluadorElegibilidad $evaluador,
    ) {
    }

    /** @return list<CamaDisponible> ordenadas por código */
    public function ejecutar(string $servicio, string $tipoCama, bool $requiereAislamiento, ContextoUsuario $u): array
    {
        $u->exigirRol(Rol::ADMISIONISTA, Rol::SUPERVISOR_CAMAS);
        $requerimiento = Requerimiento::desde($servicio, $tipoCama, $requiereAislamiento);
        $ocupadas = $this->admisiones->camasOcupadas($u->tenantId);

        $disponibles = array_values(array_filter(
            $this->catalogo->listar($u->tenantId),
            fn (CamaDisponible $c) => !in_array($c->id, $ocupadas, true)
                && $this->evaluador->motivosDeRechazo($c, $requerimiento) === [],
        ));
        usort($disponibles, fn (CamaDisponible $a, CamaDisponible $b) => strcmp($a->codigo, $b->codigo));
        return $disponibles;
    }
}
