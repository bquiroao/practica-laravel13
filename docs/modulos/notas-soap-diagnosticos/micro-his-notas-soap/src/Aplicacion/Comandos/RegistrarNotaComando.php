<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Comandos;

/**
 * Entrada del caso de uso CU-01, sin nada de HTTP.
 * claveIdempotencia (semana 5): si el cliente repite la misma petición con la misma clave,
 * no se crea otra nota; se devuelve la que ya existe.
 */
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
        public readonly ?string $claveIdempotencia = null,
    ) {
    }

    /** Huella de lo que se pidió registrar: si la misma clave llega con otra huella, es otra petición. */
    public function huella(): string
    {
        return hash('sha256', (string) json_encode([
            $this->expedienteId, $this->episodioId, $this->subjetivo, $this->objetivo, $this->analisis, $this->plan,
            strtoupper(trim($this->codigoCie10)), trim($this->descripcionDiagnostico), $this->tipoDiagnostico,
        ], JSON_UNESCAPED_UNICODE));
    }
}
