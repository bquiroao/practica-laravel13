<?php
declare(strict_types=1);

namespace Alergias\Presentacion\Api;

/** RN-07. Propuesta de permisos; en el HIS los asigna el módulo RBAC. */
final class Permisos
{
    private const POR_ROL = [
        'medico' => ['alergias.registrar', 'alerta.consultar', 'alerta.reconocer'],
        'enfermeria' => ['alerta.consultar', 'alerta.reconocer'],
    ];

    public static function permite(string $rol, string $permiso): bool
    {
        return in_array($permiso, self::POR_ROL[$rol] ?? [], true);
    }
}
