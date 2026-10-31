<?php

namespace App\Http\Requests\Clinical;

use App\Models\Hospital\Bed;
use App\Services\Admissions\AdmissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Módulo admisión (CU-01): forma de la petición. Las reglas de la cama y del paciente las valida AdmissionService. */
class AdmissionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'bed_id' => ['required', 'uuid'],
            'bed_type' => ['required', Rule::in(array_keys(Bed::TYPES))],
            'isolation' => ['required', 'boolean'],
            'ward_id' => ['nullable', 'uuid'],
            'source' => ['required', Rule::in(array_keys(AdmissionService::SOURCES))],
            'reason' => ['required', 'string', 'min:5', 'max:500', 'regex:/\S/'],
            // RNF-02 del módulo: el hospital sale de la sesión, nunca del cuerpo.
            'tenant_id' => ['prohibited'],
            'hospital_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.min' => 'Escriba el motivo del ingreso (al menos 5 letras), p. ej. «Neumonía, requiere oxígeno».',
            'tenant_id.prohibited' => 'El hospital se toma de la sesión; no lo envíe en la petición.',
            'hospital_id.prohibited' => 'El hospital se toma de la sesión; no lo envíe en la petición.',
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id' => 'paciente', 'bed_id' => 'cama', 'bed_type' => 'tipo de cama', 'isolation' => 'aislamiento', 'ward_id' => 'sala',
            'source' => 'origen', 'reason' => 'motivo del ingreso',
        ];
    }
}
