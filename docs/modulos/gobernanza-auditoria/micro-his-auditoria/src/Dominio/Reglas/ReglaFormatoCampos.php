<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Reglas;

use Auditoria\Dominio\Modelo\Rechazo;
use Auditoria\Dominio\Modelo\SolicitudRegistro;

/**
 * Semana 3: tipos y longitudes iguales a las columnas de audit_logs. Sin esta regla, un texto
 * más largo que la columna llegaría a la base y fallaría como «no disponible» en vez de «datos inválidos».
 */
final class ReglaFormatoCampos implements ReglaMovimiento
{
    private const MAXIMOS = ['event_id' => 64, 'usuario' => 64, 'modulo' => 32, 'accion' => 64, 'recurso' => 128, 'correlation_id' => 64,
        'corrige_evento' => 64];

    public function evaluar(SolicitudRegistro $s): ?Rechazo
    {
        $errores = [];
        foreach (self::MAXIMOS as $campo => $maximo) {
            $valor = $s->datos[$campo] ?? null;
            if ($valor !== null && (!is_string($valor) || mb_strlen($valor) > $maximo)) {
                $errores[$campo] = "Texto de hasta $maximo caracteres.";
            }
        }
        if (isset($s->datos['detalle']) && (!is_array($s->datos['detalle']) || array_is_list($s->datos['detalle']) && $s->datos['detalle'] !== [])) {
            $errores['detalle'] = 'Debe ser un objeto.';
        }
        if (strlen((string) json_encode($s->datos['detalle'] ?? [])) > 4096) {
            $errores['detalle'] = 'Hasta 4 KB: la bitácora guarda referencias, no documentos.';
        }

        return $errores === [] ? null : Rechazo::invalido($errores);
    }
}
