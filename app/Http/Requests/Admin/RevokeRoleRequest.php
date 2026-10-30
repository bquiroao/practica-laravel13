<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RevokeRoleRequest extends FormRequest
{
    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'max:500']];
    }
}
