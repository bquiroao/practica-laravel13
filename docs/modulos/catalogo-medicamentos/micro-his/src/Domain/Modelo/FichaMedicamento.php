<?php
declare(strict_types=1);

namespace Catalogo\Domain\Modelo;

/**
 * Datos que describen el producto. Es lo que cambia entre versiones (RN-01).
 * validar() reúne TODOS los errores de una vez (RF-02) con los límites de RN-05.
 */
final class FichaMedicamento
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $principioActivo,
        public readonly Concentracion $concentracion,
        public readonly FormaFarmaceutica $forma,
        public readonly ViaAdministracion $via,
        public readonly string $presentacion,
        public readonly ?string $registroSanitario,
    ) {
    }

    /**
     * @param array<string, mixed> $d
     * @return array{0: ?self, 1: array<string, string>} [ficha o null, errores campo => mensaje]
     */
    public static function validar(array $d): array
    {
        $e = [];
        $nombre = self::texto($d, 'nombre', Limites::NOMBRE_MIN, Limites::NOMBRE_MAX, $e);
        $principio = self::texto($d, 'principio_activo', Limites::PRINCIPIO_MIN, Limites::PRINCIPIO_MAX, $e);
        $presentacion = self::texto($d, 'presentacion', Limites::PRESENTACION_MIN, Limites::PRESENTACION_MAX, $e);
        $unidad = self::opcion($d, 'concentracion_unidad', UnidadConcentracion::class, $e);
        $forma = self::opcion($d, 'forma_farmaceutica', FormaFarmaceutica::class, $e);
        $via = self::opcion($d, 'via_administracion', ViaAdministracion::class, $e);

        $concentracion = null;
        if ($unidad !== null) {
            $concentracion = Concentracion::intentar($d['concentracion_valor'] ?? null, $unidad);
            if (is_string($concentracion)) {
                $e['concentracion_valor'] = $concentracion;
                $concentracion = null;
            }
        } elseif (!isset($d['concentracion_valor'])) {
            $e['concentracion_valor'] = 'Indique la concentración';
        }

        $registro = $d['registro_sanitario'] ?? null;
        if ($registro === '' || $registro === null) {
            $registro = null;
        } elseif (!is_string($registro) || preg_match('/^[A-Z0-9-]{4,' . Limites::REGISTRO_SANITARIO_MAX . '}$/', trim($registro)) !== 1) {
            $e['registro_sanitario'] = 'Use de 4 a 30 letras mayúsculas, números o guiones';
        } else {
            $registro = trim($registro);
        }

        if ($e !== []) {
            return [null, $e];
        }
        return [new self($nombre, $principio, $concentracion, $forma, $via, $presentacion, $registro), []];
    }

    public function clave(): ClaveEquivalencia
    {
        return ClaveEquivalencia::de($this->principioActivo, $this->concentracion, $this->forma, $this->via);
    }

    /** @param array<string, string> $e */
    private static function texto(array $d, string $campo, int $min, int $max, array &$e): string
    {
        $v = $d[$campo] ?? null;
        if (!is_string($v)) {
            $e[$campo] = 'Este dato es obligatorio';
            return '';
        }
        $v = trim($v);
        $largo = mb_strlen($v, 'UTF-8');
        if (preg_match('/[\x00-\x1F\x7F]/', $v) === 1) {
            $e[$campo] = 'No use caracteres de control';
        } elseif ($largo < $min || $largo > $max) {
            $e[$campo] = "Use entre $min y $max caracteres (tiene $largo)";
        }
        return $v;
    }

    /**
     * @template T of \BackedEnum
     * @param class-string<T> $enum
     * @param array<string, string> $e
     * @return T|null
     */
    private static function opcion(array $d, string $campo, string $enum, array &$e): ?\BackedEnum
    {
        $v = $d[$campo] ?? null;
        $opcion = is_string($v) ? $enum::tryFrom(trim($v)) : null;
        if ($opcion === null) {
            $validos = implode(', ', array_map(fn (\BackedEnum $c) => $c->value, $enum::cases()));
            $e[$campo] = "Elija un valor de la lista: $validos";
        }
        return $opcion;
    }
}
