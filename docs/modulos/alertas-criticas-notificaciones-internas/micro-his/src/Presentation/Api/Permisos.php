<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation\Api;

/** Matriz rol → acción (propuesta, por confirmar con el módulo rbac). */
final class Permisos
{
    private const MATRIZ = [
        'registrar' => ['sistema_origen'],
        'consultar' => ['enfermeria', 'medico', 'jefe_servicio', 'coordinacion'],
        'acusar' => ['enfermeria', 'medico', 'jefe_servicio', 'coordinacion'],
        'cerrar' => ['medico', 'jefe_servicio'], // RF-08
        'escalar' => ['planificador'],
    ];

    public static function permite(string $rol, string $accion): bool
    {
        return in_array($rol, self::MATRIZ[$accion] ?? [], true);
    }

    public static function matriz(): array
    {
        return self::MATRIZ;
    }
}
