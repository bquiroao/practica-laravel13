<?php

declare(strict_types=1);

namespace Pacientes\Antes;

use Pacientes\Dominio\Identificador;

/**
 * DISEÑO ANTES — violación V1 (invariante).
 *
 * Para que «todo paciente tenga un Identificador», a los pacientes sin documento se les
 * asigna este objeto. PHP lo acepta porque implementa la interfaz, pero rompe su contrato:
 * la clave es vacía y es LA MISMA para todas las personas sin documento. Quien confíe en
 * el contrato tratará a dos desconocidos distintos como el mismo paciente.
 */
final class IdentificadorTemporal implements Identificador
{
    public function tipo(): string
    {
        return 'TEMPORAL';
    }

    public function emisor(): string
    {
        return 'HIS';
    }

    public function claveBusqueda(): string
    {
        return ''; // «no aplica»
    }

    public function enmascarado(): string
    {
        return 'SIN DOCUMENTO';
    }
}
