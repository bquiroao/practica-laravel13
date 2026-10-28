<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\Resultados;

use Pacientes\Dominio\Excepciones\ErrorDominio;

/**
 * Salida de un caso de uso. Dice QUÉ pasó (creado, ok, conflicto...), no el código HTTP:
 * esa traducción es de Presentación.
 */
final class Resultado
{
    public const CREADO = 'creado';
    public const OK = 'ok';
    public const NO_DISPONIBLE = 'no_disponible';

    /** @param array<string, mixed> $datos */
    private function __construct(
        public readonly string $estado,
        public readonly array $datos,
        public readonly ?string $error = null,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public static function creado(array $datos): self
    {
        return new self(self::CREADO, $datos);
    }

    /** @param array<string, mixed> $datos */
    public static function ok(array $datos): self
    {
        return new self(self::OK, $datos);
    }

    public static function desdeError(ErrorDominio $error): self
    {
        return new self($error->categoria, $error->detalle, $error->codigo);
    }

    /** La base de datos no respondió: no se revela nada más. */
    public static function noDisponible(): self
    {
        return new self(self::NO_DISPONIBLE, [], 'SERVICIO_NO_DISPONIBLE');
    }

    public function exitoso(): bool
    {
        return $this->error === null;
    }
}
