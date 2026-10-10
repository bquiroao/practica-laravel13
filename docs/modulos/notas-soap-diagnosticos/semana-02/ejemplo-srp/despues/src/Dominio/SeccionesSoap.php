<?php

declare(strict_types=1);

namespace NotasSoap\Dominio;

use NotasSoap\Dominio\Excepciones\NotaIncompleta;

/** R-04: las cuatro secciones son obligatorias. Única razón de cambio: qué es una nota SOAP completa. */
final class SeccionesSoap
{
    public function __construct(
        public readonly string $subjetivo,
        public readonly string $objetivo,
        public readonly string $analisis,
        public readonly string $plan,
    ) {
        foreach (['subjetivo' => $subjetivo, 'objetivo' => $objetivo, 'analisis' => $analisis, 'plan' => $plan] as $nombre => $valor) {
            if (trim($valor) === '') {
                throw new NotaIncompleta(sprintf('La sección "%s" no puede estar vacía.', $nombre));
            }
        }
    }
}
