<?php
declare(strict_types=1);

namespace Lab\Application;

use DateTimeImmutable;
use Lab\Domain\DatosInvalidos;
use Lab\Domain\LabTest;
use Lab\Domain\LabTestRepository;

/** CU-04: pruebas vigentes en una fecha (por defecto hoy). */
final class ConsultarPruebasVigentes
{
    public function __construct(
        private readonly LabTestRepository $repositorio,
        private readonly Reloj $reloj,
    ) {
    }

    /** @return LabTest[] */
    public function ejecutar(string $tenantId, array $filtros = []): array
    {
        return $this->repositorio->buscarVigentes(
            $tenantId,
            self::fecha($filtros['fecha'] ?? '', $this->reloj),
            trim((string) ($filtros['texto'] ?? '')),
            ($filtros['seccion'] ?? '') !== '' ? $filtros['seccion'] : null,
            ($filtros['tipo_muestra'] ?? '') !== '' ? $filtros['tipo_muestra'] : null,
        );
    }

    public static function fecha(string $valor, Reloj $reloj): DateTimeImmutable
    {
        if ($valor === '') {
            return $reloj->ahora()->setTime(0, 0);
        }
        $f = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if (!$f || $f->format('Y-m-d') !== $valor) {
            throw new DatosInvalidos(['fecha' => 'La fecha de referencia no es válida (AAAA-MM-DD).']);
        }
        return $f;
    }
}
