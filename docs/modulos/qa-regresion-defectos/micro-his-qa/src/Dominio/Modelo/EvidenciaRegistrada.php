<?php

declare(strict_types=1);

namespace Qa\Dominio\Modelo;

/** Lo que el intento guarda de cada evidencia: su huella y dónde está. El contenido vive en el almacén. */
final readonly class EvidenciaRegistrada
{
    public function __construct(
        public string $tipo,
        public string $huella,
        public int $tamano,
        public string $clasificacion,
        public string $ubicacion,
    ) {
    }
}
