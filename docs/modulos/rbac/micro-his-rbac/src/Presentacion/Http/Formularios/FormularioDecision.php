<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Formularios;

use Rbac\Aplicacion\Comandos\ComandoAutorizar;
use Rbac\Presentacion\Http\Peticion;

/**
 * Valida el FORMATO de una consulta de decisión: {"permiso": "modulo.accion", "recurso": "..."}.
 * La identidad sale del token y el hospital de X-Tenant-ID: el cliente no puede preguntar por otro usuario.
 */
final class FormularioDecision
{
    /** @throws DatosInvalidos */
    public static function aComando(Peticion $peticion): ComandoAutorizar
    {
        $cuerpo = $peticion->cuerpo();
        $permiso = is_string($cuerpo['permiso'] ?? null) ? trim($cuerpo['permiso']) : '';
        $recurso = is_string($cuerpo['recurso'] ?? null) ? trim($cuerpo['recurso']) : '';

        $invalidos = [];
        if (preg_match('/^[a-z_]+\.[a-z_]+$/', $permiso) !== 1) {
            $invalidos[] = 'permiso';
        }
        if ($recurso === '' || strlen($recurso) > 200) {
            $invalidos[] = 'recurso';
        }
        if ($invalidos !== []) {
            throw new DatosInvalidos($invalidos);
        }

        return new ComandoAutorizar(
            $peticion->identidad->usuarioId,
            $peticion->identidad->tenantToken,
            $peticion->tenant(),
            $permiso,
            $recurso,
            $peticion->ip(),
        );
    }
}
