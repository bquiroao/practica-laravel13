<?php

declare(strict_types=1);

/*
 * Semana 12 — Cambio solicitado (brecha B-4 de la semana 11):
 * «Corregir un teléfono sin tener el documento del paciente a mano».
 * Escritas ANTES de implementar (rojo → verde). Recorren el cambio por la API real.
 */

use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Pruebas\Dobles\Escenario;

$http = static fn (object $e, string $metodo, string $ruta, string $token, ?array $cuerpo = null, array $cabeceras = []) =>
    $e->kernel->manejar(Escenario::http($metodo, $ruta, $token, $cuerpo, [], $cabeceras));

/** Lo que una interfaz puede armar SOLO con lo que devuelve el detalle (sin el documento completo). */
$cuerpoDesdeDetalle = static fn (array $p, array $cambios = []): array => array_merge([
    'nombres' => $p['nombres'] ?? null, 'apellidos' => $p['apellidos'] ?? null, 'fecha_nacimiento' => $p['nacimiento'],
    'sexo' => $p['sexo'], 'telefono' => $p['telefono'],
], $cambios);

prueba('B4-01', 'PUT sin el campo «documento»: el documento no cambia, no pide motivo y la auditoría lo muestra igual', function () use ($http): void {
    $e = Escenario::crear();
    $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', Escenario::json(Escenario::datosAna()));
    $cuerpo = Escenario::json(Escenario::datosAna(['telefono' => '+502 4444 0001']));
    unset($cuerpo['documento']);
    $r = $http($e, 'PUT', '/api/v1/pacientes/P-000001', 'token-admision-oriente', $cuerpo, ['If-Match' => '"1"']);
    igual([200, 2], [$r->estado, $r->cuerpo['version'] ?? null], 'se guarda sin motivo');
    $p = $http($e, 'GET', '/api/v1/pacientes/P-000001', 'token-admision-oriente')->cuerpo['paciente'];
    igual(['*********0101', 'DPI', false, '+502 4444 0001'], [$p['identificador'], $p['tipo_documento'], $p['identidad_provisional'], $p['telefono']], 'conserva el documento');
    $evento = $e->bitacora->eventos(EventoAuditoria::ACTUALIZADO)[0];
    igual($evento->detalle['antes']['identificador'], $evento->detalle['despues']['identificador'], 'auditoría: el documento no cambió');
});

prueba('B4-02', '«documento»: null explícito sí lo quita: es un cambio de identidad, exige motivo y queda auditado', function () use ($http): void {
    $e = Escenario::crear();
    $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', Escenario::json(Escenario::datosAna()));
    $cuerpo = Escenario::json(Escenario::datosAna(['tipo_documento' => null, 'numero_documento' => null]));
    igual(null, $cuerpo['documento'], 'el cuerpo envía documento = null');
    $r = $http($e, 'PUT', '/api/v1/pacientes/P-000001', 'token-admision-oriente', $cuerpo, ['If-Match' => '"1"']);
    igual([422, 'MOTIVO_REQUERIDO'], [$r->estado, $r->cuerpo['error'] ?? null], 'sin motivo');
    $r = $http($e, 'PUT', '/api/v1/pacientes/P-000001', 'token-admision-oriente', $cuerpo + ['motivo' => 'Documento extraviado, se completará después'], ['If-Match' => '"1"']);
    igual(200, $r->estado, 'con motivo');
    $p = $http($e, 'GET', '/api/v1/pacientes/P-000001', 'token-admision-oriente')->cuerpo['paciente'];
    igual([null, true], [$p['identificador'], $p['identidad_provisional']], 'queda como identidad provisional');
});

prueba('B4-03', 'El detalle trae nombres y apellidos por separado; reducidos (null) para quien no ve a un protegido', function () use ($http): void {
    $e = Escenario::crear();
    $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', Escenario::json(Escenario::datosAna()));
    $http($e, 'POST', '/api/v1/pacientes', 'token-supervisor-oriente', Escenario::json(Escenario::datosLuis(), ['identidad_protegida' => true, 'motivo' => 'Orden judicial']));
    $p = $http($e, 'GET', '/api/v1/pacientes/P-000001', 'token-admision-oriente')->cuerpo['paciente'];
    igual(['Ana Lucía', 'López Pérez', 'Ana Lucía López Pérez'], [$p['nombres'] ?? 'falta', $p['apellidos'] ?? 'falta', $p['nombre']], 'separados y el nombre completo se mantiene');
    $reducido = $http($e, 'GET', '/api/v1/pacientes/P-000002', 'token-clinico-oriente')->cuerpo['paciente'];
    igual([true, null, null], [array_key_exists('nombres', $reducido) && array_key_exists('apellidos', $reducido), $reducido['nombres'] ?? null, $reducido['apellidos'] ?? null], 'presentes y en null para el clínico');
    $completo = $http($e, 'GET', '/api/v1/pacientes/P-000002', 'token-supervisor-oriente')->cuerpo['paciente'];
    igual(['Luis Fernando', 'García Ruiz'], [$completo['nombres'] ?? 'falta', $completo['apellidos'] ?? 'falta'], 'completos para supervisión');
});

prueba('B4-04', 'De punta a punta: una interfaz edita el teléfono solo con lo que devuelve el detalle, y no cambia nada más', function () use ($http, $cuerpoDesdeDetalle): void {
    $e = Escenario::crear();
    $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', Escenario::json(Escenario::datosAna()));
    $leido = $http($e, 'GET', '/api/v1/pacientes/P-000001', 'token-admision-oriente');
    $p = $leido->cuerpo['paciente'];
    $r = $http($e, 'PUT', '/api/v1/pacientes/P-000001', 'token-admision-oriente', $cuerpoDesdeDetalle($p, ['telefono' => '+502 4444 0001']),
        ['If-Match' => $leido->cabeceras['ETag'] ?? '']);
    igual(200, $r->estado, 'se guarda con lo que muestra la ficha');
    $evento = $e->bitacora->eventos(EventoAuditoria::ACTUALIZADO)[0];
    $cambiaron = array_keys(array_diff_assoc(array_map('json_encode', $evento->detalle['despues']), array_map('json_encode', $evento->detalle['antes'])));
    igual(['telefono'], $cambiaron, 'solo cambió el teléfono');
});
