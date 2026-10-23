<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\Comandos;

use Pacientes\Dominio\Modelo\DatosPaciente;

/** Traduce el arreglo de entrada a DatosPaciente. Lo que falte llega vacío y lo rechaza el dominio. */
final class FormularioPaciente
{
    /** @param array<string, mixed> $datos */
    public static function aDatos(array $datos): DatosPaciente
    {
        $texto = static fn (string $clave): string => is_scalar($datos[$clave] ?? null) ? (string) $datos[$clave] : '';
        $opcional = static fn (string $clave): ?string => is_scalar($datos[$clave] ?? null) ? (string) $datos[$clave] : null;

        return new DatosPaciente(
            $texto('nombres'),
            $texto('apellidos'),
            $texto('fecha_nacimiento'),
            $texto('sexo'),
            $opcional('telefono'),
            $opcional('tipo_documento'),
            $opcional('numero_documento'),
            $opcional('pais_documento'),
            is_bool($datos['identidad_protegida'] ?? null) ? $datos['identidad_protegida'] : null,
            ($datos['documento_enviado'] ?? true) !== false,
        );
    }
}
