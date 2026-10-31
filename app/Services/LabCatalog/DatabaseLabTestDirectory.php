<?php

namespace App\Services\LabCatalog;

use App\Models\Hospital\LabTest;
use App\Services\Catalogs\LabTestDirectory;

/**
 * Módulo catálogo de pruebas de laboratorio: lo que se puede ordenar hoy (README, sección 7.1). Solo las versiones
 * vigentes en la fecha del hospital: una prueba que ya no está vigente no se puede pedir.
 */
class DatabaseLabTestDirectory implements LabTestDirectory
{
    public function __construct(private readonly LabTestCatalogService $catalog) {}

    public function all(): array
    {
        return LabTest::query()->validOn($this->today())->orderBy('name')->orderBy('code')->get()->map(fn (LabTest $t) => self::present($t))->all();
    }

    public function find(string $code): ?array
    {
        $test = $this->catalog->validOn($code, $this->today());

        return $test ? self::present($test) : null;
    }

    /** @return array<string, mixed> */
    public static function present(LabTest $t): array
    {
        $number = fn ($v) => $v === null ? null : (float) $v;

        return [
            'code' => $t->code, 'name' => $t->name, 'specimen_type' => LabTestOptions::SPECIMENS[$t->specimen_type].' · '.$t->container, 'unit' => $t->unit,
            'ref_low' => $number($t->ref_low), 'ref_high' => $number($t->ref_high), 'critical_low' => $number($t->critical_low), 'critical_high' => $number($t->critical_high),
            'section' => $t->section, 'fasting_hours' => $t->fasting_hours, 'preparation_instructions' => $t->preparation_instructions,
            'turnaround_hours' => $t->turnaround_hours,
        ];
    }

    private function today(): string
    {
        return LabTestCatalogService::today(request()->attributes->get('hospital'));
    }
}
