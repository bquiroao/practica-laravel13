<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Comandos;

/** Entrada del caso de uso CU-01, sin nada de HTTP. */
final class RegistrarNotaComando
{
    /** @param list<string> $roles */
    public function __construct(
        public readonly string $usuarioId,
        public readonly string $hospitalUsuario,
        public readonly array $roles,
        public readonly string $hospitalSolicitud,
        public readonly string $expedienteId,
        public readonly string $episodioId,
        public readonly string $subjetivo,
        public readonly string $objetivo,
        public readonly string $analisis,
        public readonly string $plan,
        public readonly string $codigoCie10,
        public readonly string $descripcionDiagnostico,
        public readonly string $tipoDiagnostico,
    ) {
    }
}
