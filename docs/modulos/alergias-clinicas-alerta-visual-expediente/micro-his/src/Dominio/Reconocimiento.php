<?php
declare(strict_types=1);

namespace Alergias\Dominio;

/** CU-05: un usuario declara que vio una alerta ALTA o CRITICA con una huella concreta (RN-09). */
final class Reconocimiento
{
    public function __construct(
        public readonly string $pacienteId,
        public readonly string $usuarioId,
        public readonly NivelAlerta $nivel,
        public readonly string $huella,
    ) {
    }
}
