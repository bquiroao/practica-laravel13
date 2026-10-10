<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Formularios;

use Rbac\Aplicacion\Comandos\ComandoAsignarRol;
use Rbac\Presentacion\Http\Peticion;

/**
 * Valida el FORMATO del cuerpo JSON y lo convierte en un comando.
 * No valida reglas de negocio (si el rol existe, duplicados, etc.): eso es del Dominio.
 */
final class FormularioAsignacion
{
    /** @throws DatosInvalidos */
    public static function aComando(Peticion $peticion): ComandoAsignarRol
    {
        $cuerpo = $peticion->cuerpo();
        $usuario = self::texto($cuerpo['usuario_id'] ?? null);
        $rol = self::texto($cuerpo['rol'] ?? null);
        $desde = self::fecha($cuerpo['valido_desde'] ?? null);
        $tieneHasta = isset($cuerpo['valido_hasta']);
        $hasta = $tieneHasta ? self::fecha($cuerpo['valido_hasta']) : null;

        $invalidos = [];
        if ($usuario === '') {
            $invalidos[] = 'usuario_id';
        }
        if ($rol === '') {
            $invalidos[] = 'rol';
        }
        if ($desde === null) {
            $invalidos[] = 'valido_desde';
        }
        if ($tieneHasta && $hasta === null) {
            $invalidos[] = 'valido_hasta';
        }
        if ($invalidos !== []) {
            throw new DatosInvalidos($invalidos);
        }

        return new ComandoAsignarRol(
            $peticion->identidad->usuarioId,
            $peticion->identidad->tenantToken,
            $peticion->tenant(),
            $usuario,
            $rol,
            $desde,
            $hasta,
            $peticion->ip(),
        );
    }

    private static function texto(mixed $valor): string
    {
        return is_string($valor) ? trim($valor) : '';
    }

    /** Solo ISO-8601 (2026-11-15 o 2026-11-15T00:00:00-06:00): DateTimeImmutable aceptaría textos como «x» o «tomorrow». */
    private static function fecha(mixed $valor): ?\DateTimeImmutable
    {
        $iso = '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})?)?$/';
        if (!is_string($valor) || preg_match($iso, $valor) !== 1) {
            return null;
        }

        try {
            return new \DateTimeImmutable($valor);
        } catch (\Exception) {
            return null;
        }
    }
}
