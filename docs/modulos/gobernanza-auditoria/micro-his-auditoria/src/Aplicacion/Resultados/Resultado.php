<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Resultados;

use Auditoria\Dominio\Modelo\Rechazo;

/**
 * Resultado de un caso de uso. El estado es una categoría de negocio; Presentación lo traduce
 * a HTTP (creado 201, ok 200, invalido 422, prohibido 403, conflicto 409, no_encontrado 404,
 * no_disponible 503).
 */
final class Resultado
{
    public const CREADO = 'creado';
    public const OK = 'ok';
    public const INVALIDO = 'invalido';
    public const PROHIBIDO = 'prohibido';
    public const CONFLICTO = 'conflicto';
    public const NO_ENCONTRADO = 'no_encontrado';
    public const NO_DISPONIBLE = 'no_disponible';

    /** @param array<string, mixed> $datos */
    private function __construct(
        public readonly string $estado,
        public readonly ?string $codigo,
        public readonly array $datos,
    ) {
    }

    public static function creado(array $datos): self
    {
        return new self(self::CREADO, null, $datos);
    }

    public static function ok(array $datos, ?string $codigo = null): self
    {
        return new self(self::OK, $codigo, $datos);
    }

    public static function error(string $estado, string $codigo, array $datos = []): self
    {
        return new self($estado, $codigo, $datos);
    }

    public static function desdeRechazo(Rechazo $rechazo): self
    {
        return new self($rechazo->categoria, $rechazo->codigo, $rechazo->campos === [] ? [] : ['campos' => $rechazo->campos]);
    }

    public static function accesoDenegado(): self
    {
        return new self(self::PROHIBIDO, 'ACCESO_DENEGADO', []);
    }

    /** La base no respondió: no se revela nada más. */
    public static function noDisponible(): self
    {
        return new self(self::NO_DISPONIBLE, 'SERVICIO_NO_DISPONIBLE', []);
    }
}
