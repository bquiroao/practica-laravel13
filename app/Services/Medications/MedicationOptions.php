<?php

namespace App\Services\Medications;

/**
 * Listas cerradas del catálogo de medicamentos (RN-05). Son las mismas que la base acepta en sus CHECK
 * (migración 2026_10_31_000400), así un dato fuera de lista da 422 y nunca un error de la base.
 */
final class MedicationOptions
{
    public const UNITS = ['mg', 'g', 'mcg', 'UI', 'MUI', 'mL', '%', 'mg/mL', 'mg/5mL'];

    public const FORMS = [
        'TABLETA' => 'Tableta', 'CAPSULA' => 'Cápsula', 'JARABE' => 'Jarabe', 'SUSPENSION' => 'Suspensión', 'SOLUCION_ORAL' => 'Solución oral',
        'SOLUCION_INYECTABLE' => 'Solución inyectable', 'POLVO_INYECTABLE' => 'Polvo para inyección', 'CREMA' => 'Crema', 'UNGUENTO' => 'Ungüento',
        'GOTAS_OFTALMICAS' => 'Gotas oftálmicas', 'INHALADOR' => 'Inhalador', 'SUPOSITORIO' => 'Supositorio',
    ];

    public const ROUTES = [
        'ORAL' => 'Oral', 'INTRAVENOSA' => 'Intravenosa', 'INTRAMUSCULAR' => 'Intramuscular', 'SUBCUTANEA' => 'Subcutánea', 'TOPICA' => 'Tópica',
        'OFTALMICA' => 'Oftálmica', 'INHALATORIA' => 'Inhalatoria', 'RECTAL' => 'Rectal',
    ];

    /** Grupo terapéutico: agrupa las recetas en los reportes. */
    public const GROUPS = [
        'ANTIBIOTICO' => 'Antibióticos', 'ANALGESICO' => 'Analgésicos y antiinflamatorios', 'CARDIOVASCULAR' => 'Cardiovascular',
        'GASTROINTESTINAL' => 'Gastrointestinal', 'ENDOCRINO' => 'Endocrino y metabólico', 'RESPIRATORIO' => 'Respiratorio',
        'SOLUCIONES' => 'Soluciones y electrolitos', 'OTRO' => 'Otros',
    ];

    private const FAMILY_LABELS = ['penicilinas' => 'Penicilinas', 'cefalosporinas' => 'Cefalosporinas', 'sulfonamidas' => 'Sulfonamidas',
        'aines' => 'AINE (antiinflamatorios no esteroideos)', 'macrolidos' => 'Macrólidos'];

    public const MAX_RESULTS = 50;

    /**
     * Familias para las alergias cruzadas. Las palabras de cada una las define prescripciones en config/nido_catalogos.php.
     *
     * @return array<string, string>
     */
    public static function allergyFamilies(): array
    {
        return collect(array_keys(config('nido_catalogos.familias_alergia', [])))
            ->mapWithKeys(fn (string $key) => [$key => self::FAMILY_LABELS[$key] ?? ucfirst($key)])->all();
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $pairs = fn (array $map) => collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();

        return [
            'units' => self::UNITS, 'forms' => $pairs(self::FORMS), 'routes' => $pairs(self::ROUTES),
            'therapeutic_groups' => $pairs(self::GROUPS), 'allergy_groups' => $pairs(self::allergyFamilies()),
        ];
    }
}
