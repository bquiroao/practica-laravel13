<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Acciones;

/** Aplana el JSON de entrada al formulario que espera la Aplicación. No valida reglas. */
final class EntradaPaciente
{
    /**
     * @param array<string, mixed> $cuerpo
     * @return array<string, mixed>
     */
    public static function datos(array $cuerpo): array
    {
        $documento = is_array($cuerpo['documento'] ?? null) ? $cuerpo['documento'] : [];

        return [
            'nombres' => $cuerpo['nombres'] ?? null,
            'apellidos' => $cuerpo['apellidos'] ?? null,
            'fecha_nacimiento' => $cuerpo['fecha_nacimiento'] ?? null,
            'sexo' => $cuerpo['sexo'] ?? null,
            'telefono' => $cuerpo['telefono'] ?? null,
            'tipo_documento' => $documento['tipo'] ?? null,
            'numero_documento' => $documento['numero'] ?? null,
            'pais_documento' => $documento['pais'] ?? null,
        ];
    }

    /** @param array<string, mixed> $cuerpo */
    public static function motivo(array $cuerpo): ?string
    {
        return is_string($cuerpo['motivo'] ?? null) ? $cuerpo['motivo'] : null;
    }
}
