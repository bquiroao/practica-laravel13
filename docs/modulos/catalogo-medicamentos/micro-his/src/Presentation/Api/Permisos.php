<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Api;

/** Matriz rol -> acciones (RNF-01). Lo que no está listado está prohibido (403). */
final class Permisos
{
    public const MATRIZ = [
        'QF_CATALOGO' => ['buscar', 'consultar', 'historial', 'opciones', 'registrar', 'version', 'baja', 'eliminar'],
        'CONSULTA_CLINICA' => ['buscar', 'consultar', 'opciones'],
        'AUDITOR_CALIDAD' => ['buscar', 'consultar', 'historial', 'opciones'],
        'SISTEMA_PRESCRIPCIONES' => ['buscar', 'consultar'],
    ];

    public static function permite(string $rol, string $accion): bool
    {
        return in_array($accion, self::MATRIZ[$rol] ?? [], true);
    }
}
