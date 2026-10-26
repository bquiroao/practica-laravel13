<?php

declare(strict_types=1);

namespace Pacientes\Dominio;

/** Documento Personal de Identificación de Guatemala (CUI de 13 dígitos). */
final class Dpi implements Identificador
{
    private function __construct(private readonly string $valor)
    {
    }

    /** @throws \InvalidArgumentException si el formato no es válido */
    public static function desde(string $texto): self
    {
        $valor = preg_replace('/[\s-]/', '', $texto) ?? '';
        if (preg_match('/^\d{13}$/', $valor) !== 1) {
            throw new \InvalidArgumentException('El DPI debe tener 13 dígitos.');
        }
        $departamento = (int) substr($valor, 9, 2);
        if ($departamento < 1 || $departamento > 22) {
            throw new \InvalidArgumentException('El departamento del DPI debe estar entre 01 y 22.');
        }

        return new self($valor);
    }

    public function tipo(): string
    {
        return 'DPI';
    }

    public function emisor(): string
    {
        return 'GTM-RENAP';
    }

    public function claveBusqueda(): string
    {
        return ClaveIdentificador::calcular($this->emisor(), $this->tipo(), $this->valor);
    }

    public function enmascarado(): string
    {
        return ClaveIdentificador::enmascarar($this->valor);
    }
}
