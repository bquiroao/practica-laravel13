<?php
declare(strict_types=1);

namespace Lab\Domain;

use DateTimeImmutable;

/** Versión de una prueba del catálogo (tabla lab_test). */
final class LabTest
{
    public const SECCIONES = [
        'hematologia', 'quimica-clinica', 'microbiologia', 'inmunologia', 'uroanalisis', 'coprologia',
    ];

    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $codigo,
        public readonly int $version,
        public readonly string $nombre,
        public readonly string $seccion,
        public readonly int $tiempoRespuestaHoras,
        public readonly RequisitoMuestra $requisito,
        public readonly Vigencia $vigencia,
        public readonly string $creadoPor,
        public readonly DateTimeImmutable $creadoEn,
    ) {
    }

    /** RN-02, RN-03 y RN-04 juntas, para devolver todos los errores a la vez. */
    public static function validar(array $d, DateTimeImmutable $hoy): void
    {
        $e = [];
        if (!preg_match('/^[A-Z]{3}-\d{3}$/', (string) ($d['codigo'] ?? ''))) {
            $e['codigo'] = 'El código debe tener el formato AAA-000 en mayúsculas.';
        }
        if (trim((string) ($d['nombre'] ?? '')) === '') {
            $e['nombre'] = 'El nombre es obligatorio.';
        }
        if (!in_array($d['seccion'] ?? '', self::SECCIONES, true)) {
            $e['seccion'] = 'Sección fuera de la lista permitida.';
        }
        if (filter_var($d['tiempo_respuesta_horas'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $e['tiempo_respuesta_horas'] = 'El tiempo de respuesta debe ser un entero mayor que 0 horas.';
        }
        $e += RequisitoMuestra::errores($d) + Vigencia::errores($d, $hoy);
        if ($e !== []) {
            throw new DatosInvalidos($e);
        }
    }

    public static function nuevaVersion(array $d, string $tenantId, int $version, string $usuario, DateTimeImmutable $ahora): self
    {
        return new self(
            Uuid::v4(), $tenantId, $d['codigo'], $version, trim($d['nombre']), $d['seccion'],
            (int) $d['tiempo_respuesta_horas'], RequisitoMuestra::desdeDatos($d), Vigencia::desdeDatos($d),
            $usuario, $ahora,
        );
    }

    /** RN-05: devuelve esta versión cerrada el día anterior a $nuevoDesde. */
    public function cerrarAntesDe(DateTimeImmutable $nuevoDesde): self
    {
        return new self(
            $this->id, $this->tenantId, $this->codigo, $this->version, $this->nombre, $this->seccion,
            $this->tiempoRespuestaHoras, $this->requisito,
            $this->vigencia->cerradaEl($nuevoDesde->modify('-1 day')), $this->creadoPor, $this->creadoEn,
        );
    }
}
