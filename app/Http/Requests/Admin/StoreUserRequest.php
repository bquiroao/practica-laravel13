<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            // Módulo usuarios/tenants: 12 o más caracteres con letra y dígito.
            'password' => ['required', 'string', 'max:255', Password::min((int) config('nido.auth.password_min_length'))->letters()->numbers()],
            'role' => ['required', 'string'],
        ];
    }
}
