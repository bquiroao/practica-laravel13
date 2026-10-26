<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Modelo;

/** Pasaporte: número alfanumérico emitido por un país (código ISO de 3 letras). */
final class Pasaporte implements Identificador
{
    private function __construct(private readonly string $valor, private readonly string $pais)
    {
    }

    /** @throws \InvalidArgumentException si el formato no es válido */
    public static function desde(string $numero, string $pais): self
    {
        $valor = strtoupper(preg_replace('/[\s-]/', '', $numero) ?? '');
        $pais = strtoupper(trim($pais));
        if (preg_match('/^[A-Z]{3}$/', $pais) !== 1) {
            throw new \InvalidArgumentException('El país del pasaporte debe ser un código de 3 letras.');
        }
        if (preg_match('/^[A-Z0-9]{6,20}$/', $valor) !== 1) {
            throw new \InvalidArgumentException('El pasaporte debe tener de 6 a 20 letras o dígitos.');
        }

        return new self($valor, $pais);
    }

    public function tipo(): string
    {
        return 'PASAPORTE';
    }

    public function emisor(): string
    {
        return $this->pais;
    }

    public function numero(): string
    {
        return $this->valor;
    }

    public function canonico(): string
    {
        return $this->emisor() . '|' . $this->tipo() . '|' . $this->valor;
    }

    public function enmascarado(): string
    {
        return str_repeat('*', strlen($this->valor) - 4) . substr($this->valor, -4);
    }
}
