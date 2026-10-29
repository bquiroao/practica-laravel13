<?php
declare(strict_types=1);

namespace Admision\Domain\Elegibilidad;

use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\Requerimiento;

/** Cerrado a modificación: solo conoce la interfaz CriterioElegibilidadCama. */
final class EvaluadorElegibilidad
{
    /** @var list<CriterioElegibilidadCama> */
    private array $criterios;

    public function __construct(CriterioElegibilidadCama ...$criterios)
    {
        $this->criterios = $criterios;
    }

    /** @return list<string> lista vacía = la cama es elegible */
    public function motivosDeRechazo(CamaDisponible $cama, Requerimiento $req): array
    {
        $motivos = [];
        foreach ($this->criterios as $criterio) {
            $motivo = $criterio->motivoDeRechazo($cama, $req);
            if ($motivo !== null) {
                $motivos[] = $motivo;
            }
        }
        return $motivos;
    }
}
