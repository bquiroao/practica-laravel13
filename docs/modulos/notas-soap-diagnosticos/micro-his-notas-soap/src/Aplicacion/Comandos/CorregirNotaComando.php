<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Comandos;

/**
 * Semana 6: entrada del caso de uso «corregir una nota firmada», sin nada de HTTP.
 * No trae expediente ni episodio: la corrección los hereda de la nota que reemplaza.
 * Semana 12: claveIdempotencia opcional; un reintento con la misma clave devuelve la misma versión nueva.
 */
final class CorregirNotaComando
{
    /** @param list<string> $roles */
    public function __construct(
        public readonly string $usuarioId,
        public readonly string $hospitalUsuario,
        public readonly array $roles,
        public readonly string $hospitalSolicitud,
        public readonly string $notaId,
        public readonly string $motivo,
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

    /** Lleva el tipo de petición y la nota corregida: la clave de un registro no sirve para una corrección. */
    public function huella(): string
    {
        return hash('sha256', (string) json_encode(['correccion', $this->notaId, $this->motivo, $this->subjetivo, $this->objetivo,
            $this->analisis, $this->plan, strtoupper(trim($this->codigoCie10)), trim($this->descripcionDiagnostico), $this->tipoDiagnostico], JSON_UNESCAPED_UNICODE));
    }
}
