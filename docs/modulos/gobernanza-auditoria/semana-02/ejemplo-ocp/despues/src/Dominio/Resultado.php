<?php

declare(strict_types=1);

namespace Auditoria\Dominio;

/**
 * Resultado de una operación. El estado es una categoría de negocio; la capa HTTP
 * (semana 3) la traduce: creado 201, ok 200, invalido 422, prohibido 403,
 * conflicto 409, no_encontrado 404, no_disponible 503.
 */
final class Resultado
{
    /** @param array<string, mixed> $datos */
    private function __construct(
        public readonly string $estado,
        public readonly ?string $codigo,
        public readonly array $datos,
    ) {
    }

    public static function creado(array $datos): self
    {
        return new self('creado', null, $datos);
    }

    public static function ok(array $datos, ?string $codigo = null): self
    {
        return new self('ok', $codigo, $datos);
    }

    public static function error(string $estado, string $codigo, array $datos = []): self
    {
        return new self($estado, $codigo, $datos);
    }

    public static function noDisponible(): self
    {
        return new self('no_disponible', 'SERVICIO_NO_DISPONIBLE', []);
    }

    /** @return array{estado: string, codigo: ?string, datos: array<string, mixed>} */
    public function comoArreglo(): array
    {
        return ['estado' => $this->estado, 'codigo' => $this->codigo, 'datos' => $this->datos];
    }
}
