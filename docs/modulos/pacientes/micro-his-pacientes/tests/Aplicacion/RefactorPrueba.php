<?php

declare(strict_types=1);

/*
 * Semana 7 — Pruebas de caracterización del refactor (Feathers, cap. 13).
 * Fijan lo que el refactor iba a mover de los casos de uso de escritura: el ORDEN en que se
 * aplican las reglas de la ficha (de él depende qué error se ve, y que un protegido no se revele)
 * y el formato exacto de los eventos de auditoría. Se escribieron y ejecutaron sobre el código
 * ANTERIOR al refactor, y deben seguir verdes después.
 */

use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Pruebas\Dobles\Escenario;

const MOTIVO_REF = 'Orden judicial de protección';

prueba('REF-01', 'Orden al editar: visibilidad → datos → edición (motivo) → confidencialidad → documento duplicado', function (): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::datosAna(['identidad_protegida' => true]), Escenario::SUPERVISOR, motivo: MOTIVO_REF);
    Escenario::registrar($e, Escenario::datosLuis());

    // Admisión no ve a la protegida: 403 aunque los datos sean inválidos (un 422 revelaría los campos).
    $r = Escenario::actualizar($e, 'P-000001', 1, Escenario::datosAna(['nombres' => '']));
    igual(['ACCESO_DENEGADO'], [$r->error], 'visibilidad antes que validación');

    // El supervisor sí la ve: ahora los datos inválidos dan 422 con el campo.
    $r = Escenario::actualizar($e, 'P-000001', 1, Escenario::datosAna(['nombres' => '']), actor: Escenario::SUPERVISOR);
    igual(['DATOS_INVALIDOS', true], [$r->error, isset($r->datos['campos']['nombres']) || isset($r->datos['nombres'])], 'validación');

    // Cambia identidad (nombre) y usa el DPI de Luis, sin motivo: primero falta el motivo (R-07)…
    $conDpiDeLuis = Escenario::datosAna(['nombres' => 'Ana María', 'numero_documento' => Escenario::DPI_LUIS]);
    igual('MOTIVO_REQUERIDO', Escenario::actualizar($e, 'P-000001', 1, $conDpiDeLuis, actor: Escenario::SUPERVISOR)->error, 'edición antes que duplicados');
    // …y con motivo, el documento ajeno (R-02).
    igual('IDENTIFICADOR_EN_USO', Escenario::actualizar($e, 'P-000001', 1, $conDpiDeLuis, 'Corrección', Escenario::SUPERVISOR)->error, 'duplicados al final');

    // Campo ausente = la protección no cambia; el campo explícito sí la cambia (con permiso y motivo).
    igual('ok', Escenario::actualizar($e, 'P-000001', 1, Escenario::datosAna(['telefono' => '+502 4444 0001']), actor: Escenario::SUPERVISOR)->estado, 'sin el campo');
    igual(true, $e->bitacora->eventos(EventoAuditoria::ACTUALIZADO)[0]->detalle['despues']['identidad_protegida'], 'sigue protegida');
    igual('MOTIVO_REQUERIDO', Escenario::actualizar($e, 'P-000001', 2, Escenario::datosAna(['identidad_protegida' => false]), actor: Escenario::SUPERVISOR)->error, 'quitar exige motivo');
});

prueba('REF-02', 'Orden al registrar: datos → confidencialidad → duplicados; el permiso de proteger se revisa antes que el documento', function (): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::datosAna());

    // Admisión intenta registrar protegida a alguien con un DPI ya usado: 403, no 409 (no confirma que existe).
    igual('ACCESO_DENEGADO', Escenario::registrar($e, Escenario::datosAna(['identidad_protegida' => true]), motivo: MOTIVO_REF)->error, 'confidencialidad antes que duplicados');
    // Datos inválidos: 422 antes que cualquier otra regla.
    igual('DATOS_INVALIDOS', Escenario::registrar($e, Escenario::datosAna(['identidad_protegida' => true, 'sexo' => 'x']))->error, 'validación primero');
    // El supervisor sí puede proteger: entonces aparece el duplicado, con el código real (él puede verlo).
    $r = Escenario::registrar($e, Escenario::datosAna(['identidad_protegida' => true]), Escenario::SUPERVISOR, motivo: MOTIVO_REF);
    igual(['PACIENTE_EXISTENTE', 'P-000001'], [$r->error, $r->datos['codigo'] ?? null], 'duplicado para el supervisor');
    // Coincidencia probable sin documento: 409 POSIBLE_DUPLICADO; confirmarla exige permiso y motivo.
    $sinDoc = Escenario::datosAna(['tipo_documento' => null, 'numero_documento' => null]);
    igual('POSIBLE_DUPLICADO', Escenario::registrar($e, $sinDoc)->error, 'advertencia');
    igual('ACCESO_DENEGADO', Escenario::registrar($e, $sinDoc, confirma: true, motivo: 'Otra persona')->error, 'confirmar sin permiso');
    igual('MOTIVO_REQUERIDO', Escenario::registrar($e, $sinDoc, Escenario::SUPERVISOR, confirma: true)->error, 'confirmar sin motivo');
});

prueba('REF-03', 'Formato exacto de los eventos PACIENTE_REGISTRADO y PACIENTE_ACTUALIZADO', function (): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::datosAna(['identidad_protegida' => true]), Escenario::SUPERVISOR, motivo: MOTIVO_REF);
    Escenario::registrar($e, Escenario::datosAna(['tipo_documento' => null, 'numero_documento' => null]), Escenario::SUPERVISOR, confirma: true, motivo: 'Homónima, otra persona');
    Escenario::actualizar($e, 'P-000001', 1, Escenario::datosAna(['nombres' => 'Ana María']), 'Corrección de nombre', Escenario::SUPERVISOR);

    [$protegida, $confirmada] = $e->bitacora->eventos(EventoAuditoria::REGISTRADO);
    igual([EventoAuditoria::REGISTRADO, Escenario::SUPERVISOR, Escenario::H_ORIENTE, 'P-000001'],
        [$protegida->accion, $protegida->actor, $protegida->hospital, $protegida->paciente], 'cabecera del evento');
    igual(['datos', 'coincidencias_confirmadas', 'motivo', 'motivo_proteccion'], array_keys($protegida->detalle), 'campos de REGISTRADO');
    igual([[], null, MOTIVO_REF], [$protegida->detalle['coincidencias_confirmadas'], $protegida->detalle['motivo'], $protegida->detalle['motivo_proteccion']], 'registro protegido');
    igual(['nombres', 'apellidos', 'nacimiento', 'sexo', 'telefono', 'identificador', 'estado', 'identidad_protegida'],
        array_keys($protegida->detalle['datos']), 'instantánea');
    igual([['P-000001'], 'Homónima, otra persona', null],
        [$confirmada->detalle['coincidencias_confirmadas'], $confirmada->detalle['motivo'], $confirmada->detalle['motivo_proteccion']], 'coincidencia confirmada');

    $actualizado = $e->bitacora->eventos(EventoAuditoria::ACTUALIZADO)[0];
    igual(['antes', 'despues', 'motivo'], array_keys($actualizado->detalle), 'campos de ACTUALIZADO');
    igual(['Ana Lucía', 'Ana María', 'Corrección de nombre'],
        [$actualizado->detalle['antes']['nombres'], $actualizado->detalle['despues']['nombres'], $actualizado->detalle['motivo']], 'antes/después');
});
