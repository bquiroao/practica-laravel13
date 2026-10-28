<?php

declare(strict_types=1);

namespace Pacientes\Dominio;

/** Datos tal como los captura el usuario, todavía sin validar. */
final class DatosPaciente
{
    public function __construct(
        public readonly string $nombres,
        public readonly string $apellidos,
        public readonly string $nacimiento,
        public readonly string $sexo,
        public readonly ?string $telefono = null,
        public readonly ?string $tipoDocumento = null,
        public readonly ?string $numeroDocumento = null,
        public readonly ?string $paisDocumento = null,
    ) {
    }
}
