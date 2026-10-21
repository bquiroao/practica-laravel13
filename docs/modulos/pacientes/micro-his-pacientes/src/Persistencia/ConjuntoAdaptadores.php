<?php

declare(strict_types=1);

namespace Pacientes\Persistencia;

use Pacientes\Aplicacion\Puertos\AlmacenIdempotencia;
use Pacientes\Aplicacion\Puertos\Transaccion;
use Pacientes\Dominio\Puertos\ConsultaPacientes;
use Pacientes\Dominio\Puertos\IndiceDuplicados;
use Pacientes\Dominio\Puertos\RegistroAuditoria;
use Pacientes\Dominio\Puertos\RepositorioPacientes;

/**
 * Un juego COHERENTE de adaptadores: comparten conexión (PDO) o almacenes (memoria),
 * así la transacción abarca al repositorio, la bitácora y la idempotencia a la vez.
 */
final class ConjuntoAdaptadores
{
    public function __construct(
        public readonly ConsultaPacientes $consulta,
        public readonly RepositorioPacientes $repositorio,
        public readonly IndiceDuplicados $indice,
        public readonly RegistroAuditoria $auditoria,
        public readonly Transaccion $transaccion,
        public readonly AlmacenIdempotencia $idempotencia,
    ) {
    }
}
