<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http\Formularios;

use E2e\Presentacion\Http\Solicitud;

/**
 * La parte común de los formularios: JSON → objeto y el tipo de cada campo. SOLO forma (400): que el digest tenga el
 * formato sha256:… o que falte un crítico en el plan son reglas, y las decide el dominio (422/409).
 */
abstract class Formulario
{
    /** @return array<string, mixed> */
    protected static function objeto(Solicitud $s): array
    {
        try {
            $datos = json_decode($s->cuerpo === '' ? '{}' : $s->cuerpo, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new EntradaInvalida('JSON_INVALIDO');
        }
        if (!is_array($datos) || ($datos !== [] && array_is_list($datos))) {
            throw new EntradaInvalida('CUERPO_INVALIDO');
        }

        return $datos;
    }

    /**
     * Tipos: 'texto', 'lista', 'objeto', 'entero', 'logico'. Los obligatorios no pueden faltar.
     * @param array<string, mixed>  $datos
     * @param array<string, string> $tipos
     * @param list<string>          $obligatorios
     */
    protected static function tipos(array $datos, array $tipos, array $obligatorios = []): void
    {
        $mal = [];
        foreach ($tipos as $campo => $tipo) {
            if (!array_key_exists($campo, $datos) || $datos[$campo] === null) {
                if (in_array($campo, $obligatorios, true)) {
                    $mal[] = $campo;
                }
                continue;
            }
            $v = $datos[$campo];
            $ok = match ($tipo) {
                'texto' => is_string($v),
                'lista' => is_array($v) && array_is_list($v),
                'objeto' => is_array($v) && ($v === [] || !array_is_list($v)),
                'entero' => is_int($v),
                'logico' => is_bool($v),
            };
            if (!$ok) {
                $mal[] = $campo;
            }
        }
        if ($mal !== []) {
            throw new EntradaInvalida('CUERPO_INVALIDO', $mal);
        }
    }
}
