<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Entrada;

use NotasSoap\Aplicacion\Comandos\CorregirNotaComando;
use NotasSoap\Presentacion\Http\Identidad;
use NotasSoap\Presentacion\Http\Solicitud;

/**
 * Semana 6: modelo de entrada de POST /api/v1/notas-soap/{id}/correcciones.
 * Igual que FormularioRegistroNota, solo revisa la FORMA (objeto, campos de texto).
 * Si el motivo alcanza, si la nota está firmada o si el CIE-10 es válido lo decide el Dominio.
 * Semana 12: lee la Idempotency-Key opcional, igual que el registro (brecha B-3).
 */
final class FormularioCorreccionNota
{
    private const CAMPOS = ['correction_reason', 'subjetivo', 'objetivo', 'analisis', 'plan'];
    private const CAMPOS_DIAGNOSTICO = ['code', 'description', 'type'];

    public static function comando(Solicitud $s, Identidad $quien, string $hospital, string $notaId): CorregirNotaComando
    {
        $b = $s->cuerpo ?? throw new CuerpoInvalido('El cuerpo debe ser un objeto JSON.');
        $dx = $b['diagnostico'] ?? null;
        if (!is_array($dx)) {
            throw new CuerpoInvalido('Falta el objeto "diagnostico" con code, description y type.');
        }
        $v = self::textos($b, self::CAMPOS, '');
        $d = self::textos($dx, self::CAMPOS_DIAGNOSTICO, 'diagnostico.');

        return new CorregirNotaComando(
            $quien->usuarioId,
            $quien->hospitalId,
            $quien->roles,
            $hospital,
            $notaId,
            $v['correction_reason'],
            $v['subjetivo'],
            $v['objetivo'],
            $v['analisis'],
            $v['plan'],
            $d['code'],
            $d['description'],
            $d['type'],
            FormularioRegistroNota::claveIdempotencia($s),
        );
    }

    /**
     * @param array<string, mixed> $origen
     * @param list<string> $campos
     * @return array<string, string>
     */
    private static function textos(array $origen, array $campos, string $prefijo): array
    {
        $salida = [];
        foreach ($campos as $campo) {
            $valor = $origen[$campo] ?? '';
            if (!is_string($valor)) {
                throw new CuerpoInvalido(sprintf('El campo "%s%s" debe ser texto.', $prefijo, $campo));
            }
            $salida[$campo] = $valor;
        }

        return $salida;
    }
}
