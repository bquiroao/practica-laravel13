<?php

declare(strict_types=1);

namespace Qa\Aplicacion\Resultados;

use Qa\Dominio\Excepciones\ReglaRechazada;

/**
 * Lo que devuelve un caso de uso, sin HTTP: la Presentación traduce el tipo a un código de estado.
 * Se llama Salida (y no Resultado) para no confundirla con el resultado de un intento (PASA, FALLA...).
 */
final readonly class Salida
{
    public const CREADO = 'CREADO';
    public const OK = 'OK';
    public const INVALIDO = 'INVALIDO';
    public const CONFLICTO = 'CONFLICTO';
    public const NO_ENCONTRADO = 'NO_ENCONTRADO';
    public const DENEGADO = 'DENEGADO';
    public const DEMASIADO_GRANDE = 'DEMASIADO_GRANDE';
    public const NO_DISPONIBLE = 'NO_DISPONIBLE';

    /** @param array<string, mixed> $datos */
    public function __construct(public string $tipo, public string $codigo, public array $datos = [])
    {
    }

    public static function denegado(): self
    {
        return new self(self::DENEGADO, 'PERMISO_DENEGADO');
    }

    public static function noEncontrado(): self
    {
        return new self(self::NO_ENCONTRADO, 'NO_ENCONTRADO');
    }

    public static function persistencia(): self
    {
        return new self(self::NO_DISPONIBLE, 'ERROR_PERSISTENCIA');
    }

    /** Códigos de regla que significan «la petición está incompleta o mal formada» (422); el resto choca con el estado (409). */
    private const INVALIDOS = ['DATOS_INVALIDOS', 'EVIDENCIA_REQUERIDA', 'DEFECTO_REQUERIDO', 'MOTIVO_REQUERIDO', 'DEFECTO_INCOMPLETO',
        'ALCANCE_INCOMPLETO', 'TIPO_NO_PERMITIDO', 'EVIDENCIA_VACIA', 'URL_INVALIDA', 'CASO_INEXISTENTE', 'RESULTADO_NO_VERIFICA'];

    public static function rechazo(ReglaRechazada $e): self
    {
        return new self(in_array($e->codigo, self::INVALIDOS, true) ? self::INVALIDO : self::CONFLICTO, $e->codigo,
            $e->detalle === [] ? [] : ['detalle' => $e->detalle]);
    }
}
