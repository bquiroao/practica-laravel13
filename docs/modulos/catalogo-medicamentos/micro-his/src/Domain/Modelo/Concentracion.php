<?php
declare(strict_types=1);

namespace Catalogo\Domain\Modelo;

/**
 * Cantidad y unidad. El valor se guarda como texto decimal normalizado ("500", "0.9") para que
 * la comparación de duplicados no dependa de flotantes. Cabe en numeric(9,3).
 */
final class Concentracion
{
    private function __construct(public readonly string $valor, public readonly UnidadConcentracion $unidad)
    {
    }

    /** @return self|string el objeto o el mensaje de error del valor */
    public static function intentar(mixed $valor, UnidadConcentracion $unidad): self|string
    {
        $texto = is_int($valor) || is_float($valor) ? (string) $valor : (is_string($valor) ? trim($valor) : '');
        $texto = str_replace(',', '.', $texto);
        $patron = sprintf('/^\d{1,%d}(\.\d{1,%d})?$/', Limites::CONCENTRACION_ENTEROS, Limites::CONCENTRACION_DECIMALES);
        if (preg_match($patron, $texto) !== 1) {
            return 'Use un número mayor que 0, hasta 999999.999 y con máximo 3 decimales';
        }
        $normalizado = self::normalizar($texto);
        if ($normalizado === '0') {
            return 'La concentración debe ser mayor que 0';
        }
        return new self($normalizado, $unidad);
    }

    /** Para filas leídas de la base ("500.000" -> "500"). */
    public static function reconstituir(string $valor, string $unidad): self
    {
        return new self(self::normalizar($valor), UnidadConcentracion::from($unidad));
    }

    private static function normalizar(string $texto): string
    {
        [$entero, $decimal] = array_pad(explode('.', $texto, 2), 2, '');
        $entero = ltrim($entero, '0') === '' ? '0' : ltrim($entero, '0');
        $decimal = rtrim($decimal, '0');
        return $decimal === '' ? $entero : "$entero.$decimal";
    }

    public function texto(): string
    {
        return $this->valor . ' ' . $this->unidad->value;
    }
}
