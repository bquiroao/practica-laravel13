<?php

declare(strict_types=1);

namespace E2e\Aplicacion\Resultados;

use E2e\Dominio\Excepciones\ReglaRechazada;

/** Lo que devuelve un caso de uso, sin HTTP: la Presentación traduce el tipo a un código de estado. */
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

    /** Reglas que dicen «la petición está mal o incompleta» (422); las demás chocan con el estado (409). */
    private const INVALIDOS = ['DATOS_INVALIDOS', 'DATOS_NO_SINTETICOS', 'PLAN_INCOMPLETO', 'ESCENARIO_INEXISTENTE', 'TIPO_NO_PERMITIDO',
        'EVIDENCIA_VACIA', 'MOTIVO_REQUERIDO', 'ESCENARIOS_SIN_RESULTADO'];

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

    public static function rechazo(ReglaRechazada $e): self
    {
        $tipo = match (true) {
            $e->codigo === 'EVIDENCIA_DEMASIADO_GRANDE' => self::DEMASIADO_GRANDE,
            in_array($e->codigo, self::INVALIDOS, true) => self::INVALIDO,
            default => self::CONFLICTO,
        };

        return new self($tipo, $e->codigo, $e->detalle === [] ? [] : ['detalle' => $e->detalle]);
    }
}
