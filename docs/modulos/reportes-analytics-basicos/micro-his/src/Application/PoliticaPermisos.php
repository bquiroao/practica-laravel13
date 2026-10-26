<?php
declare(strict_types=1);

namespace MicroHis\Application;

/** Qué reportes puede consultar cada rol (RN-01). El Médico solo ve Citas (RN-08). */
final class PoliticaPermisos
{
    private const PERMISOS = [
        'administrador' => ['*'],
        'jefe_area' => ['*'],
        'medico' => ['citas'],
    ];

    public function exigir(string $rol, string $tipo): void
    {
        $permitidos = self::PERMISOS[$rol] ?? [];
        if (!in_array('*', $permitidos, true) && !in_array($tipo, $permitidos, true)) {
            throw new AccesoDenegado("El rol «{$rol}» no tiene permiso para el reporte «{$tipo}» (RN-01)");
        }
    }
}
