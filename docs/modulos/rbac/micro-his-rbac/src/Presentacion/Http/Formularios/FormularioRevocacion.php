<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Formularios;

use Rbac\Aplicacion\Comandos\ComandoRevocarRol;
use Rbac\Presentacion\Http\Peticion;

/**
 * Valida el FORMATO de {"usuario_id", "rol", "motivo"}. Que el motivo sea suficiente
 * o que la membresía exista son reglas del Dominio, no del formulario.
 */
final class FormularioRevocacion
{
    /** @throws DatosInvalidos */
    public static function aComando(Peticion $peticion): ComandoRevocarRol
    {
        $cuerpo = $peticion->cuerpo();
        $texto = static fn (mixed $v): string => is_string($v) ? trim($v) : '';
        $usuario = $texto($cuerpo['usuario_id'] ?? null);
        $rol = $texto($cuerpo['rol'] ?? null);
        $motivo = $texto($cuerpo['motivo'] ?? null);

        $invalidos = array_keys(array_filter(
            ['usuario_id' => $usuario, 'rol' => $rol, 'motivo' => $motivo],
            static fn (string $valor): bool => $valor === '' || mb_strlen($valor) > 500,
        ));
        if ($invalidos !== []) {
            throw new DatosInvalidos($invalidos);
        }

        return new ComandoRevocarRol(
            $peticion->identidad->usuarioId,
            $peticion->identidad->tenantToken,
            $peticion->tenant(),
            $usuario,
            $rol,
            $motivo,
            $peticion->ip(),
        );
    }
}
