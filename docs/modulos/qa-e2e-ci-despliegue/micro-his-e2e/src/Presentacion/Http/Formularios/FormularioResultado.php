<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http\Formularios;

use E2e\Presentacion\Http\Solicitud;

/**
 * POST /ejecuciones/{id}/resultados: Idempotency-Key obligatoria y artefactos en base64. Decodificar es FORMA (400);
 * si el tipo de artefacto está permitido o si pesa demasiado lo decide el dominio.
 */
final class FormularioResultado extends Formulario
{
    /** @return array{clave: string, datos: array<string, mixed>} */
    public static function desde(Solicitud $s): array
    {
        $clave = (string) $s->cabecera('idempotency-key');
        if ($clave === '') {
            throw new EntradaInvalida('IDEMPOTENCY_KEY_REQUERIDA');
        }
        $d = self::objeto($s);
        self::tipos($d, ['escenario' => 'texto', 'resultado' => 'texto', 'intentos' => 'entero', 'evidencias' => 'lista'], ['escenario', 'resultado', 'intentos']);
        $artefactos = [];
        foreach ($d['evidencias'] ?? [] as $i => $a) {
            if (!is_array($a) || !is_string($a['tipo'] ?? null) || !is_string($a['contenido_base64'] ?? null)) {
                throw new EntradaInvalida('CUERPO_INVALIDO', ["evidencias[$i]"]);
            }
            $bytes = base64_decode($a['contenido_base64'], true);
            if ($bytes === false) {
                throw new EntradaInvalida('BASE64_INVALIDO', ["evidencias[$i]"]);
            }
            $artefactos[] = ['tipo' => $a['tipo'], 'bytes' => $bytes];
        }

        return ['clave' => $clave, 'datos' => ['evidencias' => $artefactos] + $d];
    }

    public static function cierre(Solicitud $s): ?string
    {
        $d = self::objeto($s);
        self::tipos($d, ['abortar' => 'texto']);

        return $d['abortar'] ?? null;
    }
}
