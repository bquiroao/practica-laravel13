<?php

declare(strict_types=1);

// Los mismos datos ficticios de database/datos-demo.sql, para los adaptadores en memoria.
return [
    'expedientes' => [
        'exp-carlos' => ['paciente' => 'pac-carlos', 'abierto' => true],
        'exp-rosa' => ['paciente' => 'pac-rosa', 'abierto' => false],
    ],
    'episodios' => ['epi-100' => 'pac-carlos', 'epi-200' => 'pac-rosa'],
    'asignaciones' => ['epi-100' => ['dra-mendez'], 'epi-200' => ['dra-mendez']],
];
