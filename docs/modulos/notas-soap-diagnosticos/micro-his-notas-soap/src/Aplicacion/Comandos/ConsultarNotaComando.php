<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Comandos;

final class ConsultarNotaComando
{
    public function __construct(
        public readonly string $hospitalUsuario,
        public readonly string $hospitalSolicitud,
        public readonly string $notaId,
    ) {
    }
}
