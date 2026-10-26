<?php

declare(strict_types=1);

namespace NotasSoap\Dominio;

/** Datos de entrada del caso de uso, ya separados de HTTP. */
final class SolicitudRegistroNota
{
    public function __construct(
        public readonly string $tenantId,
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
