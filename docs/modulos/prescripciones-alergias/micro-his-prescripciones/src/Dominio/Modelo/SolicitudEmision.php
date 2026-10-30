<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Modelo;

/**
 * Lo que el médico pide emitir. Cada ítem: medicamento, dosis, unidad, frecuencia_horas, via, dias.
 * No trae «resultado de la verificación previa»: el servidor nunca lo acepta del cliente (R-03).
 */
final readonly class SolicitudEmision
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(
        public string $usuario,
        public string $hospital,
        public string $paciente,
        public string $clave,
        public array $items,
        public ?string $justificacion = null,
        public bool $confirmaSinAlergias = false,
    ) {
    }

    /** Huella del contenido, para distinguir un reintento de un uso indebido de la clave (R-11). */
    public function huella(): string
    {
        return hash('sha256', (string) json_encode([$this->paciente, $this->items, $this->justificacion, $this->confirmaSinAlergias]));
    }
}
