<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Formularios;

use Qa\Presentacion\Http\Solicitud;

/**
 * POST /campanas/{id}/resultados: Idempotency-Key obligatoria y evidencias en base64. Decodificar es FORMA (400);
 * que la captura sea de verdad un PNG o que pese menos de 5 MB lo deciden el dominio y el caso de uso.
 */
final class FormularioResultado extends Formulario
{
    /** @return array{clave: string, datos: array<string, mixed>} */
    public static function desde(Solicitud $s): array
    {
        $clave = $s->cabecera('idempotency-key');
        if ($clave === null || !preg_match('/^[A-Za-z0-9-]{8,64}$/', $clave)) {
            throw new EntradaInvalida('IDEMPOTENCY_KEY_REQUERIDA');
        }
        $d = self::objeto($s);
        self::tipos($d, ['caso' => 'texto', 'resultado' => 'texto', 'evidencias' => 'lista', 'referencias' => 'lista', 'defecto' => 'objeto',
            'verifica_defecto' => 'entero', 'motivo' => 'texto'], ['caso', 'resultado']);
        $evidencias = [];
        foreach ($d['evidencias'] ?? [] as $n => $e) {
            $bytes = is_array($e) && is_string($e['contenido_base64'] ?? null) ? base64_decode($e['contenido_base64'], true) : false;
            if ($bytes === false) {
                throw new EntradaInvalida('EVIDENCIA_BASE64_INVALIDA', ["evidencias[$n]"]);
            }
            $evidencias[] = ['tipo' => (string) ($e['tipo'] ?? ''), 'bytes' => $bytes, 'clasificacion' => (string) ($e['clasificacion'] ?? 'GENERAL')];
        }
        $d['evidencias'] = $evidencias;

        return ['clave' => $clave, 'datos' => $d];
    }
}
