<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Eventos;

use NotasSoap\Dominio\Modelo\NotaSoap;

/**
 * Semana 7: el ÚNICO lugar que define el nombre y los campos de cada evento de la bitácora del módulo.
 * Antes, cada caso de uso armaba su propio arreglo ('NOTA_REGISTRADA', 'NOTA_FIRMADA', 'NOTA_CORREGIDA').
 * Solo lleva identificadores, nunca texto clínico (RNF-03).
 */
final class EntradaBitacora
{
    /** @param array<string, string> $datos */
    private function __construct(
        public readonly string $evento,
        public readonly array $datos,
    ) {
    }

    public static function notaRegistrada(NotaSoap $nota): self
    {
        return new self('NOTA_REGISTRADA', ['nota_id' => $nota->id, 'autor_id' => $nota->autorId]);
    }

    public static function notaFirmada(NotaSoap $nota): self
    {
        return new self('NOTA_FIRMADA', ['nota_id' => $nota->id, 'autor_id' => $nota->autorId]);
    }

    public static function notaCorregida(NotaSoap $nota): self
    {
        if ($nota->reemplazaA === null) {
            throw new \LogicException('Solo una corrección (con supersedes_id) produce NOTA_CORREGIDA.');
        }

        return new self('NOTA_CORREGIDA', ['nota_id' => $nota->id, 'reemplaza_a' => $nota->reemplazaA, 'autor_id' => $nota->autorId]);
    }
}
