<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Entrada;

use NotasSoap\Aplicacion\Comandos\RegistrarNotaComando;
use NotasSoap\Presentacion\Http\Identidad;
use NotasSoap\Presentacion\Http\Solicitud;

/**
 * Modelo de entrada del MVC: convierte el JSON de la petición en un comando de la Aplicación.
 * Solo revisa la FORMA (que sea objeto, que los campos sean texto). No decide si una sección
 * vacía o un CIE-10 son válidos: eso es regla de negocio y vive en el Dominio.
 * El autor y el hospital salen siempre de la Identidad (token), nunca del cuerpo (RF-06).
 */
final class FormularioRegistroNota
{
    private const CAMPOS = ['medical_record_id', 'care_episode_id', 'subjetivo', 'objetivo', 'analisis', 'plan'];
    private const CAMPOS_DIAGNOSTICO = ['code', 'description', 'type'];

    public static function comando(Solicitud $s, Identidad $quien, string $hospital): RegistrarNotaComando
    {
        $b = $s->cuerpo ?? throw new CuerpoInvalido('El cuerpo debe ser un objeto JSON.');
        $dx = $b['diagnostico'] ?? null;
        if (!is_array($dx)) {
            throw new CuerpoInvalido('Falta el objeto "diagnostico" con code, description y type.');
        }
        $v = self::textos($b, self::CAMPOS, '');
        $d = self::textos($dx, self::CAMPOS_DIAGNOSTICO, 'diagnostico.');

        return new RegistrarNotaComando(
            $quien->usuarioId,
            $quien->hospitalId,
            $quien->roles,
            $hospital,
            $v['medical_record_id'],
            $v['care_episode_id'],
            $v['subjetivo'],
            $v['objetivo'],
            $v['analisis'],
            $v['plan'],
            $d['code'],
            $d['description'],
            $d['type'],
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
