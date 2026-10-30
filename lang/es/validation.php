<?php

// Mensajes de validación en español para las reglas que usa la API de Hospital Nido.
return [
    'after_or_equal' => 'El campo :attribute debe ser una fecha igual o posterior a :date.',
    'array' => 'El campo :attribute debe ser una lista.',
    'between' => [
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'date' => 'El campo :attribute no es una fecha válida.',
    'distinct' => 'El campo :attribute tiene un valor repetido.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'in' => 'El valor de :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
        'array' => 'El campo :attribute no debe tener más de :max elementos.',
    ],
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
    ],
    'password' => [
        'letters' => 'La contraseña debe contener al menos una letra.',
        'mixed' => 'La contraseña debe contener mayúsculas y minúsculas.',
        'numbers' => 'La contraseña debe contener al menos un número.',
        'symbols' => 'La contraseña debe contener al menos un símbolo.',
        'uncompromised' => 'La contraseña apareció en una filtración de datos; elija otra.',
    ],
    'present' => 'El campo :attribute debe estar presente.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'uuid' => 'El campo :attribute debe ser un identificador válido.',

    'attributes' => [
        'name' => 'nombre',
        'email' => 'correo',
        'password' => 'contraseña',
        'role' => 'rol',
        'user_id' => 'usuario',
        'hospital_id' => 'hospital',
        'valid_from' => 'vigente desde',
        'valid_to' => 'vigente hasta',
        'motivo' => 'motivo',
        'permissions' => 'permisos',
        'status' => 'estado',
        'desde' => 'desde',
        'hasta' => 'hasta',
    ],
];
