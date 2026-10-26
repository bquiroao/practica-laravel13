<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Modelo;

/** Único lugar que conoce los tipos concretos de documento. */
final class FabricaIdentificador
{
    /** @throws \InvalidArgumentException si el documento no es válido */
    public static function crear(?string $tipo, ?string $numero, ?string $pais = null): ?Identificador
    {
        $tipo = strtoupper(trim((string) $tipo));
        $numero = trim((string) $numero);
        if ($tipo === '' && $numero === '') {
            return null; // sin documento: identidad provisional
        }

        return match ($tipo) {
            'DPI' => Dpi::desde($numero),
            'PASAPORTE' => Pasaporte::desde($numero, (string) $pais),
            '' => throw new \InvalidArgumentException('Indique el tipo de documento.'),
            default => throw new \InvalidArgumentException("Tipo de documento no admitido: $tipo."),
        };
    }
}
