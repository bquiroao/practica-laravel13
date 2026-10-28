<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Comandos;

/** Entrada de la consulta de notas de un expediente (la usa, por ejemplo, la línea de tiempo del Expediente). */
final class ListarNotasExpedienteComando
{
    public function __construct(
        public readonly string $hospitalUsuario,
        public readonly string $hospitalSolicitud,
        public readonly string $expedienteId,
        public readonly ?string $estado = null,
    ) {
    }
}
