<?php
declare(strict_types=1);

namespace Lab\Application;

use Lab\Domain\LabTest;
use Lab\Domain\LabTestRepository;
use Lab\Domain\PruebaNoVigente;

/** CU-05: detalle y requisitos de una prueba vigente en la fecha. */
final class VerDetallePrueba
{
    public function __construct(
        private readonly LabTestRepository $repositorio,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(string $tenantId, string $codigo, string $fecha = ''): LabTest
    {
        $f = ConsultarPruebasVigentes::fecha($fecha, $this->reloj);
        $prueba = $this->repositorio->buscarVigentePorCodigo($tenantId, $codigo, $f);
        if ($prueba !== null) {
            return $prueba;
        }
        $ultima = $this->repositorio->ultimaVersion($tenantId, $codigo);
        throw new PruebaNoVigente(
            $ultima === null
                ? "La prueba $codigo no existe en el catálogo."
                : "La prueba $codigo no está vigente el {$f->format('Y-m-d')}; su última versión es vigente {$ultima->vigencia->texto()}.",
            $ultima?->vigencia,
        );
    }
}
