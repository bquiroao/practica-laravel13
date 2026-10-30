<?php

declare(strict_types=1);

/*
 * Los mismos datos de demostración que datos-demo.sql, para los adaptadores en memoria (QA_PERSISTENCIA=memoria).
 * INT-10 comprueba que los dos archivos describen lo mismo.
 */

$paso = static fn (string $accion, string $esperado): array => ['accion' => $accion, 'esperado' => $esperado];

return [
    // [tenant, caso, autor, título, precondiciones, pasos, prioridad, requisitos, aprobador|null]
    'casos' => [
        ['hosp-norte', 'TC-101', 'ana-qa', 'C1 identidad de red: correo nulo permitido y único sin mayúsculas', 'Base central de prueba migrada',
            [$paso('Insertar dos usuarios con correo NULL', 'Ambos se insertan'), $paso('Insertar Ana@x.gt y ana@x.gt', 'El segundo se rechaza (23505)')], 'ALTA', ['RF-01'], 'lider-qa'],
        ['hosp-norte', 'TC-102', 'ana-qa', 'C2 MPI: un merge exige candidatos coherentes', 'Base central de prueba migrada',
            [$paso('Registrar un merge con candidato de otro paciente', 'Se rechaza por la restricción de coherencia')], 'ALTA', ['RF-02'], 'lider-qa'],
        ['hosp-norte', 'TC-103', 'ana-qa', 'C3 consentimiento: break-glass exige revisión encadenada', 'Base central de prueba migrada',
            [$paso('Agregar una revisión a un acceso normal', 'Se rechaza'), $paso('Actualizar una revisión', 'Se rechaza: solo agregar')], 'ALTA', ['RF-03'], 'lider-qa'],
        ['hosp-norte', 'TC-104', 'ana-qa', 'C4 sincronización: fecha terminal posterior a la recepción', 'Base central de prueba migrada',
            [$paso('Marcar procesado un mensaje antes de available_at', 'Se rechaza')], 'MEDIA', ['RF-04'], 'lider-qa'],
        ['hosp-norte', 'TC-105', 'ana-qa', 'H1 identidad hospitalaria: licencia única por profesional', 'Base hospitalaria de prueba migrada',
            [$paso('Registrar dos licencias activas iguales', 'La segunda se rechaza')], 'MEDIA', ['RF-05'], 'lider-qa'],
        ['hosp-norte', 'TC-106', 'ana-qa', 'H3 agenda y camas: sin dos estancias activas en la misma cama', 'Base hospitalaria de prueba migrada',
            [$paso('Asignar una cama ocupada en un rango que se solapa', 'Se rechaza por exclusión')], 'ALTA', ['RF-06'], 'lider-qa'],
        ['hosp-norte', 'TC-107', 'ana-qa', 'H4 expediente: una nota firmada no se edita', 'Base hospitalaria con una nota firmada',
            [$paso('Actualizar el texto de la nota firmada', 'Se rechaza: se agrega una corrección')], 'ALTA', ['RF-07'], 'lider-qa'],
        ['hosp-norte', 'TC-108', 'ana-qa', 'H4 alergias: una alergia activa equivalente no se duplica', 'Paciente con alergia activa a penicilina',
            [$paso('Registrar otra alergia activa a penicilina', 'Se rechaza por equivalencia')], 'ALTA', ['RF-07', 'RF-08'], 'lider-qa'],
        ['hosp-norte', 'TC-109', 'ana-qa', 'H4 signos vitales: rango fuera de lo posible', 'Episodio abierto',
            [$paso('Registrar temperatura de 60 °C', 'Se rechaza por rango')], 'BAJA', ['RF-07'], 'lider-qa'],
        ['hosp-norte', 'TC-110', 'ana-qa', 'Reversión H4: rollback de un paso deja H1–H3', 'Base hospitalaria migrada completa',
            [$paso('migrate:rollback --step=1', 'Solo desaparecen las tablas de H4')], 'MEDIA', ['RF-09'], 'lider-qa'],
        ['hosp-norte', 'TC-111', 'ana-qa', 'H2 atención: alcance JSONB válido (borrador)', 'Base hospitalaria de prueba migrada',
            [$paso('Asignar atención con scope vacío', 'Se rechaza')], 'MEDIA', ['RF-06'], null],
        ['hosp-sur', 'TC-101', 'qa-sur', 'C1 identidad de red (hospital sur)', 'Base central de prueba migrada',
            [$paso('Insertar dos usuarios con correo NULL', 'Ambos se insertan')], 'ALTA', ['RF-01'], 'lider-sur'],
    ],
    // [tenant, funcionalidad, caso, crítico]
    'matriz' => [
        ['hosp-norte', 'identidad-central', 'TC-101', true],
        ['hosp-norte', 'mpi', 'TC-102', true],
        ['hosp-norte', 'consentimiento', 'TC-103', true],
        ['hosp-norte', 'sincronizacion', 'TC-104', false],
        ['hosp-norte', 'identidad-hospital', 'TC-105', false],
        ['hosp-norte', 'agenda-camas', 'TC-106', true],
        ['hosp-norte', 'expediente', 'TC-107', true],
        ['hosp-norte', 'expediente', 'TC-108', true],
        ['hosp-norte', 'expediente', 'TC-109', false],
        ['hosp-norte', 'expediente', 'TC-110', false],
        ['hosp-sur', 'identidad-central', 'TC-101', true],
    ],
];
