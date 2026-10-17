<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Comandos;

/**
 * Semana 6: entrada del caso de uso «corregir una nota firmada», sin nada de HTTP.
 * No trae expediente ni episodio: la corrección los hereda de la nota que reemplaza.
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
    ) {
    }
}
