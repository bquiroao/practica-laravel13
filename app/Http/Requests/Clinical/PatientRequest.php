<?php

namespace App\Http\Requests\Clinical;

use App\Models\Hospital\LocalPatient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Registro y edición de la ficha. El DPI y el pasaporte se validan en PatientService (reglas del módulo). */
class PatientRequest extends FormRequest
{
    public function rules(): array
    {
        $editing = $this->isMethod('PUT');

        return [
            'given_name' => ['required', 'string', 'max:100', 'regex:/\p{L}/u'],
            'family_name' => ['required', 'string', 'max:100', 'regex:/\p{L}/u'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1900-01-01'],
            'sex' => ['required', Rule::in(array_keys(LocalPatient::SEXES))],
            'document_type' => ['nullable', Rule::in(['DPI', 'PASAPORTE'])],
            'document_number' => ['nullable', 'string', 'max:30'],
            'document_country' => ['nullable', 'string', 'max:3'],
            'contact' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:200'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'motivo' => ['nullable', 'string', 'max:500'],
            'confirmar_duplicado' => ['sometimes', 'boolean'],
            'status' => [$editing ? 'sometimes' : 'prohibited', Rule::in(array_keys(LocalPatient::STATUSES))],
            'version' => [$editing ? 'required' : 'prohibited', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'given_name' => 'nombres', 'family_name' => 'apellidos', 'birth_date' => 'fecha de nacimiento', 'sex' => 'sexo',
            'document_type' => 'tipo de documento', 'document_number' => 'número de documento', 'document_country' => 'país del documento',
            'contact' => 'teléfono', 'address' => 'dirección', 'emergency_contact_name' => 'contacto de emergencia',
            'emergency_contact_phone' => 'teléfono de emergencia', 'status' => 'estado',
        ];
    }

    public function messages(): array
    {
        return [
            'given_name.regex' => 'Los nombres deben tener al menos una letra.',
            'family_name.regex' => 'Los apellidos deben tener al menos una letra.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'birth_date.date_format' => 'Use el formato de fecha AAAA-MM-DD.',
        ];
    }
}
