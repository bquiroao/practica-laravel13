<?php

namespace App\Http\Requests\Clinical;

use App\Services\Allergies\AllergyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Módulo alergias clínicas (CU-02): RN-01, RN-02, RN-04 y RN-05. Un campo que no es texto se rechaza en lugar de
 * descartarse (corrige el defecto D-03 de la semana 15). El duplicado (RN-03) lo valida AllergyService.
 */
class AllergyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'allergen_name' => ['nullable', 'string', 'max:120', 'required_without:allergen_code', 'regex:/\p{L}/u'],
            'allergen_code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'allergy_type' => ['required', Rule::in(array_keys(AllergyService::TYPES))],
            'severity' => ['required', Rule::in(array_keys(AllergyService::SEVERITIES))],
            'reaction' => ['nullable', 'string', 'max:500', 'regex:/\S/'],
            'onset_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1900-01-01'],
        ];
    }

    public function messages(): array
    {
        return [
            'allergen_name.required_without' => 'Escriba el alérgeno (por ejemplo «Penicilina») o su código.',
            'allergen_code.regex' => 'El código lleva solo letras, números, puntos o guiones (por ejemplo J01CA04).',
            'reaction.regex' => 'La reacción no puede quedar en blanco. Si no se conoce, deje el campo vacío.',
            'onset_date.before_or_equal' => 'La fecha de inicio no puede ser futura.',
        ];
    }

    public function attributes(): array
    {
        return [
            'allergen_name' => 'alérgeno', 'allergen_code' => 'código del alérgeno', 'allergy_type' => 'tipo', 'severity' => 'severidad',
            'reaction' => 'reacción', 'onset_date' => 'fecha de inicio',
        ];
    }
}
