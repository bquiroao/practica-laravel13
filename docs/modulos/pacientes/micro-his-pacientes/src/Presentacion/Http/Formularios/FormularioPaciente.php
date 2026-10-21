<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Formularios;

use Pacientes\Aplicacion\Comandos\ComandoActualizar;
use Pacientes\Aplicacion\Comandos\ComandoBuscar;
use Pacientes\Aplicacion\Comandos\ComandoConsultar;
use Pacientes\Aplicacion\Comandos\ComandoRegistrar;
use Pacientes\Presentacion\Http\Identidad;
use Pacientes\Presentacion\Http\Solicitud;

/**
 * Valida el FORMATO de la entrada (JSON, tipos, If-Match) y construye el comando.
 * Si el nombre falta o la fecha es imposible lo decide el Dominio (422), no el formulario.
 */
final class FormularioPaciente
{
    private const TEXTOS = ['nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'telefono', 'motivo'];
    private const DOCUMENTO = ['tipo', 'numero', 'pais'];
    private const CRITERIOS = ['codigo', 'tipo_documento', 'numero_documento', 'pais_documento', 'nombre', 'nacimiento'];

    /** @throws EntradaInvalida */
    public static function registro(Solicitud $solicitud, Identidad $identidad): ComandoRegistrar
    {
        $cuerpo = self::cuerpo($solicitud);
        $confirma = $cuerpo['confirmar_coincidencia'] ?? false;
        if (!is_bool($confirma)) {
            throw new EntradaInvalida(EntradaInvalida::FORMATO, ['confirmar_coincidencia' => 'Debe ser true o false.']);
        }

        return new ComandoRegistrar($identidad->actor, $identidad->hospital, self::datos($cuerpo), $confirma, $cuerpo['motivo'] ?? null);
    }

    /** @throws EntradaInvalida */
    public static function actualizacion(Solicitud $solicitud, Identidad $identidad, string $codigo): ComandoActualizar
    {
        $ifMatch = $solicitud->cabecera('If-Match');
        if ($ifMatch === '') {
            throw new EntradaInvalida(EntradaInvalida::VERSION_REQUERIDA);
        }
        if (preg_match('/^(?:W\/)?"?(\d{1,9})"?$/', $ifMatch, $m) !== 1) {
            throw new EntradaInvalida(EntradaInvalida::VERSION_INVALIDA);
        }
        $cuerpo = self::cuerpo($solicitud);

        return new ComandoActualizar($identidad->actor, $identidad->hospital, $codigo, (int) $m[1], self::datos($cuerpo), $cuerpo['motivo'] ?? null);
    }

    public static function busqueda(Solicitud $solicitud, Identidad $identidad): ComandoBuscar
    {
        return new ComandoBuscar($identidad->actor, $identidad->hospital, array_intersect_key($solicitud->consulta, array_flip(self::CRITERIOS)));
    }

    public static function consulta(Identidad $identidad, string $codigo): ComandoConsultar
    {
        return new ComandoConsultar($identidad->actor, $identidad->hospital, $codigo);
    }

    /**
     * @return array<string, mixed>
     * @throws EntradaInvalida
     */
    private static function cuerpo(Solicitud $solicitud): array
    {
        if ($solicitud->cuerpo === null) {
            throw new EntradaInvalida(EntradaInvalida::CUERPO);
        }

        $errores = [];
        foreach (self::TEXTOS as $campo) {
            if (array_key_exists($campo, $solicitud->cuerpo) && !is_string($solicitud->cuerpo[$campo]) && $solicitud->cuerpo[$campo] !== null) {
                $errores[$campo] = 'Debe ser texto.';
            }
        }
        $documento = $solicitud->cuerpo['documento'] ?? null;
        if ($documento !== null && !is_array($documento)) {
            $errores['documento'] = 'Debe ser un objeto {tipo, numero, pais} o null.';
        } elseif (is_array($documento)) {
            foreach (self::DOCUMENTO as $campo) {
                if (isset($documento[$campo]) && !is_string($documento[$campo])) {
                    $errores['documento.' . $campo] = 'Debe ser texto.';
                }
            }
        }
        if ($errores !== []) {
            throw new EntradaInvalida(EntradaInvalida::FORMATO, $errores);
        }

        return $solicitud->cuerpo;
    }

    /**
     * @param array<string, mixed> $cuerpo
     * @return array<string, ?string>
     */
    private static function datos(array $cuerpo): array
    {
        $documento = is_array($cuerpo['documento'] ?? null) ? $cuerpo['documento'] : [];

        return [
            'nombres' => $cuerpo['nombres'] ?? null,
            'apellidos' => $cuerpo['apellidos'] ?? null,
            'fecha_nacimiento' => $cuerpo['fecha_nacimiento'] ?? null,
            'sexo' => $cuerpo['sexo'] ?? null,
            'telefono' => $cuerpo['telefono'] ?? null,
            'tipo_documento' => $documento['tipo'] ?? null,
            'numero_documento' => $documento['numero'] ?? null,
            'pais_documento' => $documento['pais'] ?? null,
        ];
    }
}
