<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Formularios;

use Qa\Presentacion\Http\Solicitud;

/**
 * Lectura de la entrada HTTP, común a todos los formularios: JSON → objeto, y los tipos de cada campo.
 * Solo forma (400): «el título está vacío» o «falta un crítico» son reglas y las decide el dominio.
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
     * Comprueba el tipo de cada campo presente: 'texto', 'lista', 'objeto', 'entero', 'numero'. Los obligatorios no pueden faltar.
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
                'numero' => is_int($v) || is_float($v),
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
