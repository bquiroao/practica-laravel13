<?php
declare(strict_types=1);

namespace Lab\Domain;

use DateTimeImmutable;

/** Puerto de persistencia: lo define el dominio y lo implementan los adaptadores. */
interface LabTestRepository
{
    /** Última versión registrada del código en el hospital, o null. */
    public function ultimaVersion(string $tenantId, string $codigo): ?LabTest;

    /** RN-07: guarda la nueva versión y, si viene, el cierre de la anterior, en una sola transacción. */
    public function guardarNuevaVersion(LabTest $nueva, ?LabTest $anteriorCerrada): void;

    /** RN-06: pruebas vigentes en la fecha, con filtros opcionales. @return LabTest[] */
    public function buscarVigentes(string $tenantId, DateTimeImmutable $fecha, string $texto = '', ?string $seccion = null, ?string $tipoMuestra = null): array;

    /** RN-06: versión del código vigente en la fecha, o null. */
    public function buscarVigentePorCodigo(string $tenantId, string $codigo, DateTimeImmutable $fecha): ?LabTest;
}
