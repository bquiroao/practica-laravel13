<?php

namespace App\Http\Requests\Clinical;

use App\Services\Medications\MedicationOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Módulo catálogo de medicamentos: la ficha de un alta (CU-01) o de una versión nueva (CU-03), con los mismos límites
 * que las columnas de la base (RN-05). Cada campo se valida por separado, así todos los errores llegan en una sola
 * respuesta: una concentración «0» sin unidad informa los dos errores a la vez (corrige el defecto D-01 de la semana 15).
 * El código, el hospital y el usuario no se reciben: los asigna el sistema (RN-06). El duplicado (RN-04) lo valida el servicio.
 */
class MedicationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sanitary_registration'))) {
            $this->merge(['sanitary_registration' => mb_strtoupper($this->input('sanitary_registration'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150', 'regex:/\p{L}/u'],
            'active_ingredient' => ['required', 'string', 'min:3', 'max:120', 'regex:/\p{L}/u'],
            'strength_value' => ['required', 'numeric', 'gt:0', 'max:999999.999', 'regex:/^\d{1,6}(\.\d{1,3})?$/'],
            'strength_unit' => ['required', 'string', Rule::in(MedicationOptions::UNITS)],
            'dosage_form' => ['required', 'string', Rule::in(array_keys(MedicationOptions::FORMS))],
            'route' => ['required', 'string', Rule::in(array_keys(MedicationOptions::ROUTES))],
            'presentation' => ['required', 'string', 'min:3', 'max:120'],
            'sanitary_registration' => ['nullable', 'string', 'regex:/^[A-Z0-9-]{4,30}$/'],
            'therapeutic_group' => ['required', 'string', Rule::in(array_keys(MedicationOptions::GROUPS))],
            'allergy_groups' => ['nullable', 'array'],
            'allergy_groups.*' => ['string', 'distinct', Rule::in(array_keys(MedicationOptions::allergyFamilies()))],
            'valid_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2099-12-31'],
            'version' => ['nullable', 'integer', 'between:1,999'],
        ];
    }

    public function messages(): array
    {
        return [
            'strength_value.gt' => 'La concentración debe ser mayor que 0.',
            'strength_value.max' => 'La concentración no puede pasar de 999 999,999.',
            'strength_value.regex' => 'La concentración lleva hasta 6 enteros y 3 decimales, con punto (por ejemplo 0.9 o 500).',
            'strength_unit.in' => 'Elija una unidad de la lista (mg, g, mcg, UI, MUI, mL, %, mg/mL o mg/5mL).',
            'sanitary_registration.regex' => 'El registro sanitario lleva de 4 a 30 letras, números o guiones (por ejemplo RS-0107).',
            'name.regex' => 'El nombre debe llevar letras.',
            'active_ingredient.regex' => 'El principio activo debe llevar letras.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre', 'active_ingredient' => 'principio activo', 'strength_value' => 'concentración', 'strength_unit' => 'unidad',
            'dosage_form' => 'forma farmacéutica', 'route' => 'vía de administración', 'presentation' => 'presentación',
            'sanitary_registration' => 'registro sanitario', 'therapeutic_group' => 'grupo terapéutico', 'allergy_groups' => 'familias de alergia',
            'allergy_groups.*' => 'familia de alergia', 'valid_from' => 'vigente desde', 'version' => 'versión',
        ];
    }
}
