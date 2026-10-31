<?php

namespace App\Services\Medications;

use App\Models\Hospital\Medication;
use App\Services\Catalogs\MedicationDirectory;

/**
 * Módulo catálogo de medicamentos: lo que prescripciones puede recetar hoy (README, sección 7.6). Solo las versiones
 * vigentes en la fecha del hospital; un medicamento dado de baja ya no aparece ni se encuentra por su código.
 */
class DatabaseMedicationDirectory implements MedicationDirectory
{
    public function __construct(private readonly MedicationCatalogService $catalog) {}

    public function all(): array
    {
        return Medication::query()->validOn($this->today())->orderBy('name')->orderBy('code')->get()->map(fn (Medication $m) => self::present($m))->all();
    }

    public function find(string $code): ?array
    {
        $medication = $this->catalog->validOn($code, $this->today());

        return $medication ? self::present($medication) : null;
    }

    /** @return array{code: string, name: string, presentation: string, route: string, groups: list<string>, active_ingredient: string, therapeutic_group: string} */
    public static function present(Medication $m): array
    {
        return [
            'code' => $m->code, 'name' => $m->name, 'presentation' => $m->presentation, 'route' => mb_strtolower(MedicationOptions::ROUTES[$m->route]),
            'groups' => $m->allergy_groups, 'active_ingredient' => $m->active_ingredient, 'therapeutic_group' => $m->therapeutic_group,
        ];
    }

    private function today(): string
    {
        return MedicationCatalogService::today(request()->attributes->get('hospital'));
    }
}
