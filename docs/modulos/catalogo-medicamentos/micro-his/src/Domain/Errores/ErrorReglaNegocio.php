<?php
declare(strict_types=1);

namespace Catalogo\Domain\Errores;

/**
 * Una regla del catálogo impide la operación. La presentación lo traduce a 409.
 * Códigos: DUPLICADO (RN-04), SOLAPE_VIGENCIA (RN-01), YA_DADO_DE_BAJA (RN-01),
 * EN_USO (RN-02), CONFLICTO_CONCURRENTE, INTEGRIDAD (RNF-04).
 */
final class ErrorReglaNegocio extends ErrorDominio
{
}
