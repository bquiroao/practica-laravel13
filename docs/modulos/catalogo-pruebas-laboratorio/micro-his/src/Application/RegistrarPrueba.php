<?php
declare(strict_types=1);

namespace Lab\Application;

use Lab\Domain\LabTest;
use Lab\Domain\LabTestRepository;
use Lab\Domain\VigenciaSuperpuesta;

/** CU-01 (incluye CU-02 y CU-03): alta de una prueba o de una nueva versión. */
final class RegistrarPrueba
{
    public function __construct(
        private readonly LabTestRepository $repositorio,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(array $datos, string $tenantId, string $usuario): LabTest
    {
        $ahora = $this->reloj->ahora();
        LabTest::validar($datos, $ahora);                                   // RN-02, RN-03, RN-04

        $ultima = $this->repositorio->ultimaVersion($tenantId, $datos['codigo']);
        $nueva = LabTest::nuevaVersion($datos, $tenantId, ($ultima?->version ?? 0) + 1, $usuario, $ahora);

        $anteriorCerrada = null;
        if ($ultima !== null && $ultima->vigencia->seSuperponeCon($nueva->vigencia)) {   // RN-05
            if ($nueva->vigencia->desde <= $ultima->vigencia->desde) {
                throw new VigenciaSuperpuesta(sprintf(
                    'La prueba %s ya tiene la versión %d vigente %s.',
                    $ultima->codigo, $ultima->version, $ultima->vigencia->texto(),
                ));
            }
            $anteriorCerrada = $ultima->cerrarAntesDe($nueva->vigencia->desde);
        }

        $this->repositorio->guardarNuevaVersion($nueva, $anteriorCerrada);  // RN-07, RN-08
        return $nueva;
    }
}
