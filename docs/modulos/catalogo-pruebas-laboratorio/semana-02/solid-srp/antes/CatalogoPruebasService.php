<?php
declare(strict_types=1);

namespace Antes;

/**
 * ANTES: una sola clase con cuatro motivos para cambiar:
 * 1) validar datos, 2) decidir versiones y vigencias, 3) guardar, 4) armar el mensaje para la pantalla.
 */
final class CatalogoPruebasService
{
    /** Simula la tabla lab_test; en el original aquí había SQL armado a mano. */
    public array $tabla = [];

    public function registrar(array $d, string $hoy): string
    {
        // 1) Validación mezclada con lo demás
        if (!preg_match('/^[A-Z]{3}-\d{3}$/', $d['codigo'] ?? '')) {
            return 'ERROR: código inválido';
        }
        if (($d['volumen_minimo_ml'] ?? 0) <= 0) {
            return 'ERROR: volumen inválido';
        }
        if (($d['vigente_desde'] ?? '') < $hoy) {
            return 'ERROR: fecha desde anterior a hoy';
        }
        // 2) Regla de versiones mezclada con el acceso a datos
        $ultima = null;
        foreach ($this->tabla as $i => $fila) {
            if ($fila['code'] === $d['codigo'] && ($ultima === null || $fila['version'] > $this->tabla[$ultima]['version'])) {
                $ultima = $i;
            }
        }
        $version = 1;
        if ($ultima !== null) {
            $u = $this->tabla[$ultima];
            if ($u['valid_to'] === null || $u['valid_to'] >= $d['vigente_desde']) {
                if ($d['vigente_desde'] <= $u['valid_from']) {
                    return 'ERROR: vigencia superpuesta';
                }
                // 3) Persistencia directa
                $this->tabla[$ultima]['valid_to'] = date('Y-m-d', strtotime($d['vigente_desde'] . ' -1 day'));
            }
            $version = $u['version'] + 1;
        }
        $this->tabla[] = [
            'code' => $d['codigo'], 'version' => $version, 'min_volume_ml' => $d['volumen_minimo_ml'],
            'valid_from' => $d['vigente_desde'], 'valid_to' => null,
        ];
        // 4) Formato de salida para la vista
        return "OK: {$d['codigo']} versión $version registrada";
    }
}
