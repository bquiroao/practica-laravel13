<?php
declare(strict_types=1);

namespace Lab\Domain;

/** RN-03: requisitos de muestra y preparación del paciente. */
final class RequisitoMuestra
{
    public const TIPOS = ['sangre-total', 'suero', 'plasma', 'orina', 'heces', 'hisopado'];
    public const CONSERVACION = ['ambiente', 'refrigerada', 'congelada'];

    public function __construct(
        public readonly string $tipoMuestra,
        public readonly string $contenedor,
        public readonly float $volumenMinimoMl,
        public readonly int $horasAyuno,
        public readonly string $instruccionesPreparacion,
        public readonly string $conservacion,
        public readonly int $estabilidadHoras,
    ) {
    }

    /** @return array<string,string> */
    public static function errores(array $d): array
    {
        $e = [];
        if (!in_array($d['tipo_muestra'] ?? '', self::TIPOS, true)) {
            $e['tipo_muestra'] = 'Tipo de muestra fuera de la lista permitida.';
        }
        if (trim((string) ($d['contenedor'] ?? '')) === '') {
            $e['contenedor'] = 'El contenedor es obligatorio.';
        }
        if (!is_numeric($d['volumen_minimo_ml'] ?? null) || (float) $d['volumen_minimo_ml'] <= 0) {
            $e['volumen_minimo_ml'] = 'El volumen mínimo debe ser mayor que 0 mL.';
        }
        if (filter_var($d['horas_ayuno'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            $e['horas_ayuno'] = 'Las horas de ayuno deben ser un entero de 0 o más.';
        }
        if (!in_array($d['conservacion'] ?? '', self::CONSERVACION, true)) {
            $e['conservacion'] = 'Conservación fuera de la lista permitida.';
        }
        if (filter_var($d['estabilidad_horas'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $e['estabilidad_horas'] = 'La estabilidad debe ser un entero mayor que 0 horas.';
        }
        return $e;
    }

    public static function desdeDatos(array $d): self
    {
        return new self(
            $d['tipo_muestra'],
            trim((string) $d['contenedor']),
            (float) $d['volumen_minimo_ml'],
            (int) $d['horas_ayuno'],
            trim((string) ($d['instrucciones_preparacion'] ?? '')),
            $d['conservacion'],
            (int) $d['estabilidad_horas'],
        );
    }
}
