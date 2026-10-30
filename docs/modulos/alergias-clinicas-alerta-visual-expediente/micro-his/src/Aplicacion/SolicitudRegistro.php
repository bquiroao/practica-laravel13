<?php
declare(strict_types=1);

namespace Alergias\Aplicacion;

/** Datos de entrada de CU-01, ya separados de la forma en que llegaron. */
final class SolicitudRegistro
{
    public function __construct(
        public readonly string $codigoPaciente,
        public readonly ?string $alergenoCodigo,
        public readonly ?string $alergenoNombre,
        public readonly string $tipo,
        public readonly string $severidad,
        public readonly ?string $reaccion,
        public readonly ?string $fechaInicio,
        public readonly string $usuarioId,
    ) {
    }
}
