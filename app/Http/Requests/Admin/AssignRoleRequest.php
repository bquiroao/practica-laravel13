<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid'],
            'role' => ['required', 'string'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
        ];
    }
}
