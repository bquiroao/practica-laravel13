<?php

namespace App\Http\Requests\Clinical;

use App\Services\LabCatalog\LabTestOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Módulo catálogo de pruebas de laboratorio: la ficha de un alta o de una versión nueva, con RN-02 a RN-04 y los
 * mismos límites que las columnas, así un dato demasiado largo o grande da 422 y no 500 (corrige D-02 y D-03).
 * Que la fecha desde no sea anterior a hoy (RN-04) lo valida el servicio, con la fecha del hospital.
 */
class LabTestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $number = ['nullable', 'numeric', 'between:-99999999,99999999', 'regex:/^-?\d{1,8}(\.\d{1,4})?$/'];

        return [
            // En una versión nueva el código sale de la ruta y lo que venga en el cuerpo se ignora.
            'code' => $this->route('code') === null ? ['required', 'string', 'regex:'.LabTestOptions::CODE_PATTERN] : ['nullable'],
            'name' => ['required', 'string', 'min:2', 'max:'.LabTestOptions::NAME_MAX, 'regex:/\p{L}/u'],
            'section' => ['required', 'string', Rule::in(array_keys(LabTestOptions::SECTIONS))],
            'turnaround_hours' => ['required', 'integer', 'between:1,720'],
            'specimen_type' => ['required', 'string', Rule::in(array_keys(LabTestOptions::SPECIMENS))],
            'container' => ['required', 'string', 'min:2', 'max:'.LabTestOptions::CONTAINER_MAX],
            'min_volume_ml' => ['required', 'numeric', 'gt:0', 'max:'.LabTestOptions::VOLUME_MAX, 'regex:/^\d{1,4}(\.\d{1,2})?$/'],
            'fasting_hours' => ['required', 'integer', 'between:0,72'],
            'preparation_instructions' => ['nullable', 'string', 'max:'.LabTestOptions::PREPARATION_MAX],
            'storage' => ['required', 'string', Rule::in(array_keys(LabTestOptions::STORAGE))],
            'stability_hours' => ['required', 'integer', 'between:1,8760'],
            'unit' => ['required', 'string', 'max:20'],
            'ref_low' => $number, 'ref_high' => $number, 'critical_low' => $number, 'critical_high' => $number,
            'valid_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:2099-12-31'],
            'valid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from', 'before_or_equal:2099-12-31'],
            'version' => ['nullable', 'integer', 'between:1,999'],
        ];
    }

    /** Los rangos tienen que tener sentido entre sí: crítico bajo < referencia baja ≤ referencia alta < crítico alto. */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['ref_low', 'ref_high', 'critical_low', 'critical_high'])) {
                return;
            }
            $v = fn (string $k) => $this->filled($k) ? (float) $this->input($k) : null;
            [$low, $high, $critLow, $critHigh] = [$v('ref_low'), $v('ref_high'), $v('critical_low'), $v('critical_high')];
            if ($low !== null && $high !== null && $low > $high) {
                $validator->errors()->add('ref_high', 'La referencia alta debe ser mayor o igual que la baja.');
            }
            if ($critLow !== null && $low !== null && $critLow >= $low) {
                $validator->errors()->add('critical_low', 'El crítico bajo debe ser menor que la referencia baja.');
            }
            if ($critHigh !== null && $high !== null && $critHigh <= $high) {
                $validator->errors()->add('critical_high', 'El crítico alto debe ser mayor que la referencia alta.');
            }
            if ($critLow !== null && $critHigh !== null && $critLow >= $critHigh) {
                $validator->errors()->add('critical_high', 'El crítico alto debe ser mayor que el crítico bajo.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'El código lleva tres letras, un guion y tres números, por ejemplo HEM-001.',
            'min_volume_ml.gt' => 'El volumen mínimo debe ser mayor que 0 mL.',
            'min_volume_ml.max' => 'El volumen mínimo no puede pasar de 9999.99 mL.',
            'min_volume_ml.regex' => 'El volumen lleva hasta 4 enteros y 2 decimales, con punto (por ejemplo 0.5).',
            'stability_hours.between' => 'La estabilidad debe ser de 1 a 8760 horas.',
            'turnaround_hours.between' => 'El tiempo de respuesta debe ser de 1 a 720 horas.',
            'valid_to.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
            'ref_low.regex' => 'Hasta 8 enteros y 4 decimales, con punto.', 'ref_high.regex' => 'Hasta 8 enteros y 4 decimales, con punto.',
            'critical_low.regex' => 'Hasta 8 enteros y 4 decimales, con punto.', 'critical_high.regex' => 'Hasta 8 enteros y 4 decimales, con punto.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'código', 'name' => 'nombre', 'section' => 'sección', 'turnaround_hours' => 'tiempo de respuesta', 'specimen_type' => 'tipo de muestra',
            'container' => 'contenedor', 'min_volume_ml' => 'volumen mínimo', 'fasting_hours' => 'horas de ayuno', 'preparation_instructions' => 'preparación del paciente',
            'storage' => 'conservación', 'stability_hours' => 'estabilidad', 'unit' => 'unidad', 'ref_low' => 'referencia baja', 'ref_high' => 'referencia alta',
            'critical_low' => 'crítico bajo', 'critical_high' => 'crítico alto', 'valid_from' => 'fecha desde', 'valid_to' => 'fecha hasta', 'version' => 'versión',
        ];
    }
}
