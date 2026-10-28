<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Formularios;

use Rbac\Presentacion\Http\Peticion;

/**
 * Valida el FORMATO de {"permisos": ["modulo.accion", ...]}: una lista de 1 a 20 permisos bien escritos.
 * Como en FormularioDecision, el usuario sale del token: no se puede preguntar por otra persona.
 */
final class FormularioPermisosEfectivos
{
    public const MAXIMO = 20;

    /**
     * @return list<string>
     * @throws DatosInvalidos
     */
    public static function aLista(Peticion $peticion): array
    {
        $permisos = $peticion->cuerpo()['permisos'] ?? null;
        $valida = is_array($permisos) && array_is_list($permisos)
            && count($permisos) >= 1 && count($permisos) <= self::MAXIMO
            && array_filter($permisos, static fn (mixed $p): bool => !is_string($p) || preg_match('/^[a-z_]+\.[a-z_]+$/', $p) !== 1) === [];
        if (!$valida) {
            throw new DatosInvalidos(['permisos']);
        }

        return array_values(array_unique($permisos));
    }
}
