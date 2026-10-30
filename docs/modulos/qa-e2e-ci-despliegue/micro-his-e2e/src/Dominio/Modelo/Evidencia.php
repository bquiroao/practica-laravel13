<?php

declare(strict_types=1);

namespace E2e\Dominio\Modelo;

use E2e\Dominio\Excepciones\ReglaRechazada;

/** Un artefacto de la ejecución, direccionado por su SHA-256 (R-08). El contenido vive en el almacén, no en la base. */
final readonly class Evidencia
{
    public const TIPOS = ['traza', 'captura', 'video', 'log', 'junit'];
    public const TAMANO_MAXIMO = 20 * 1024 * 1024;

    public function __construct(public string $tipo, public string $sha256, public int $tamano, public string $ubicacion)
    {
    }

    /** Valida lo que el pipeline manda ANTES de tocar el almacén. */
    public static function validar(string $tipo, string $bytes): void
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new ReglaRechazada('TIPO_NO_PERMITIDO', [$tipo]);
        }
        if ($bytes === '') {
            throw new ReglaRechazada('EVIDENCIA_VACIA');
        }
        if (strlen($bytes) > self::TAMANO_MAXIMO) {
            throw new ReglaRechazada('EVIDENCIA_DEMASIADO_GRANDE');
        }
    }
}
