<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Modelo;

use NotasSoap\Dominio\Excepciones\NotaIncompleta;

/** R-04: las cuatro secciones son obligatorias. */
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

    /** Formato con el que se guarda en clinical_notes.content (la tabla tiene un solo campo de texto). */
    public function comoContenido(): string
    {
        return (string) json_encode([
            'S' => $this->subjetivo, 'O' => $this->objetivo, 'A' => $this->analisis, 'P' => $this->plan,
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function desdeContenido(string $contenido): self
    {
        $d = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);

        return new self((string) $d['S'], (string) $d['O'], (string) $d['A'], (string) $d['P']);
    }
}
