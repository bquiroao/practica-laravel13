<?php
declare(strict_types=1);

namespace Despues;

/** Responsabilidad única: decidir si los datos de la prueba son válidos (RN-02 a RN-04). */
final class Requisitos
{
    /** @return string[] errores; vacío si todo es válido */
    public static function errores(array $d, string $hoy): array
    {
        $e = [];
        if (!preg_match('/^[A-Z]{3}-\d{3}$/', $d['codigo'] ?? '')) {
            $e[] = 'código inválido';
        }
        if (($d['volumen_minimo_ml'] ?? 0) <= 0) {
            $e[] = 'volumen inválido';
        }
        if (($d['vigente_desde'] ?? '') < $hoy) {
            $e[] = 'fecha desde anterior a hoy';
        }
        return $e;
    }
}
