<?php

declare(strict_types=1);

/*
 * Semana 6 — Cambio práctico: «paciente con identidad protegida».
 * Escritas ANTES de implementar (rojo → verde). Recorren el cambio por la API real.
 */

use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Pruebas\Dobles\Escenario;
use Pacientes\Pruebas\Dobles\RegistroAccesoEnMemoria;

const MOTIVO_PROTECCION = 'Orden judicial de protección (víctima de violencia)';

$http = static fn (object $e, string $metodo, string $ruta, string $token, ?array $cuerpo = null, array $consulta = [], array $cabeceras = []) =>
    $e->kernel->manejar(Escenario::http($metodo, $ruta, $token, $cuerpo, $consulta, $cabeceras));

/** Ana registrada como protegida por el supervisor; Luis, normal. */
$escenarioProtegido = static function (?RegistroAccesoEnMemoria $registro = null) use ($http): object {
    $e = Escenario::crear($registro);
    $r = $http($e, 'POST', '/api/v1/pacientes', 'token-supervisor-oriente',
        Escenario::json(Escenario::datosAna(), ['identidad_protegida' => true, 'motivo' => MOTIVO_PROTECCION]));
    if ($r->estado !== 201) {
        throw new RuntimeException('no se pudo preparar el escenario: ' . json_encode($r->cuerpo));
    }
    $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', Escenario::json(Escenario::datosLuis()));

    return $e;
};

prueba('PROT-01', 'Identidad: proteger exige pacientes.proteger y motivo; queda auditado con el motivo', function () use ($http): void {
    $e = Escenario::crear();
    $proteger = Escenario::json(Escenario::datosAna(), ['identidad_protegida' => true, 'motivo' => MOTIVO_PROTECCION]);
    $sinPermiso = $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $proteger);
    igual([403, 'ACCESO_DENEGADO'], [$sinPermiso->estado, $sinPermiso->cuerpo['error'] ?? null], 'admisión no puede proteger');
    $sinMotivo = $http($e, 'POST', '/api/v1/pacientes', 'token-supervisor-oriente', Escenario::json(Escenario::datosAna(), ['identidad_protegida' => true]));
    igual([422, 'MOTIVO_REQUERIDO'], [$sinMotivo->estado, $sinMotivo->cuerpo['error'] ?? null], 'sin motivo');
    $ok = $http($e, 'POST', '/api/v1/pacientes', 'token-supervisor-oriente', $proteger);
    igual(201, $ok->estado, 'supervisor con motivo');
    $evento = $e->bitacora->eventos(EventoAuditoria::REGISTRADO)[0];
    igual([true, MOTIVO_PROTECCION], [$evento->detalle['datos']['identidad_protegida'] ?? null, $evento->detalle['motivo_proteccion'] ?? null], 'auditoría');
    igual(400, $http($e, 'POST', '/api/v1/pacientes', 'token-supervisor-oriente', Escenario::json(Escenario::datosLuis(), ['identidad_protegida' => 'si']))->estado, 'formato booleano');
});

prueba('PROT-02', 'Búsqueda: por nombre no aparece sin permiso; por código o documento aparece con datos reducidos', function () use ($http, $escenarioProtegido): void {
    $e = $escenarioProtegido();
    igual(0, $http($e, 'GET', '/api/v1/pacientes', 'token-clinico-oriente', consulta: ['nombre' => 'lopez'])->cuerpo['total'], 'clínico por nombre');
    igual(1, $http($e, 'GET', '/api/v1/pacientes', 'token-supervisor-oriente', consulta: ['nombre' => 'lopez'])->cuerpo['total'], 'supervisor por nombre');
    $porCodigo = $http($e, 'GET', '/api/v1/pacientes', 'token-clinico-oriente', consulta: ['codigo' => 'P-000001'])->cuerpo['resultados'][0] ?? [];
    igual(['codigo' => 'P-000001', 'nombre' => 'Identidad protegida', 'nacimiento' => null, 'identificador' => null, 'identidad_protegida' => true],
        $porCodigo, 'resumen reducido por código');
    $porDpi = $http($e, 'GET', '/api/v1/pacientes', 'token-clinico-oriente', consulta: ['tipo_documento' => 'DPI', 'numero_documento' => Escenario::DPI_ANA]);
    igual([1, 'Identidad protegida'], [$porDpi->cuerpo['total'], $porDpi->cuerpo['resultados'][0]['nombre'] ?? null], 'por documento');
    igual(false, $http($e, 'GET', '/api/v1/pacientes', 'token-clinico-oriente', consulta: ['nombre' => 'garcia'])->cuerpo['resultados'][0]['identidad_protegida'] ?? null, 'un paciente normal no cambia');
});

prueba('PROT-03', 'Detalle: sin permiso, sin demografía (nombre, nacimiento, teléfono, documento) y auditado como acceso limitado', function () use ($http, $escenarioProtegido): void {
    $e = $escenarioProtegido();
    $clinico = $http($e, 'GET', '/api/v1/pacientes/P-000001', 'token-clinico-oriente');
    igual(200, $clinico->estado, 'el clínico puede atenderlo');
    $p = $clinico->cuerpo['paciente'];
    igual(['Identidad protegida', null, null, null, null, true],
        [$p['nombre'], $p['nacimiento'], $p['telefono'], $p['identificador'], $p['tipo_documento'], $p['identidad_protegida']], 'detalle reducido');
    igual('Ana Lucía López Pérez', $http($e, 'GET', '/api/v1/pacientes/P-000001', 'token-supervisor-oriente')->cuerpo['paciente']['nombre'], 'supervisor completo');
    $consultas = $e->bitacora->eventos(EventoAuditoria::CONSULTADO);
    igual([true, false], [$consultas[0]->detalle['acceso_limitado'] ?? null, $consultas[1]->detalle['acceso_limitado'] ?? null], 'auditoría del tipo de acceso');
});

prueba('PROT-04', 'Edición: sin permiso de lectura protegida no se edita; quitar la protección exige pacientes.proteger y motivo', function () use ($http, $escenarioProtegido): void {
    $e = $escenarioProtegido();
    $put = static fn (string $token, array $cambios, ?string $motivo, string $version) => $http($e, 'PUT', '/api/v1/pacientes/P-000001', $token,
        array_merge(Escenario::json(Escenario::datosAna()), $cambios, ['motivo' => $motivo]), cabeceras: ['If-Match' => $version]);
    igual([403, 'ACCESO_DENEGADO'], [($r = $put('token-admision-oriente', ['identidad_protegida' => true], null, '"1"'))->estado, $r->cuerpo['error'] ?? null], 'admisión no edita a un protegido');
    igual([422, 'MOTIVO_REQUERIDO'], [($r = $put('token-supervisor-oriente', ['identidad_protegida' => false], null, '"1"'))->estado, $r->cuerpo['error'] ?? null], 'quitar sin motivo');
    // Un PUT que NO envía el campo no puede quitar la protección por omisión.
    igual(200, $put('token-supervisor-oriente', ['telefono' => '+502 4444 0001'], null, '"1"')->estado, 'editar el teléfono sin el campo');
    igual(0, $http($e, 'GET', '/api/v1/pacientes', 'token-clinico-oriente', consulta: ['nombre' => 'lopez'])->cuerpo['total'], 'sigue protegida');
    igual(200, $put('token-supervisor-oriente', ['identidad_protegida' => false], 'Se levantó la orden judicial', '"2"')->estado, 'supervisor quita con motivo');
    $eventos = $e->bitacora->eventos(EventoAuditoria::ACTUALIZADO);
    $evento = end($eventos);
    igual([true, false], [$evento->detalle['antes']['identidad_protegida'], $evento->detalle['despues']['identidad_protegida']], 'antes/después');
    igual(1, $http($e, 'GET', '/api/v1/pacientes', 'token-clinico-oriente', consulta: ['nombre' => 'lopez'])->cuerpo['total'], 'vuelve a aparecer');
    // Proteger a un paciente existente también exige el permiso.
    $luis = $http($e, 'PUT', '/api/v1/pacientes/P-000002', 'token-admision-oriente', Escenario::json(Escenario::datosLuis(), ['identidad_protegida' => true, 'motivo' => MOTIVO_PROTECCION]), cabeceras: ['If-Match' => '"1"']);
    igual(403, $luis->estado, 'admisión no puede proteger a Luis');
});

prueba('PROT-05', 'Duplicados: se siguen detectando, pero el 409 no revela el código del protegido a quien no tiene permiso', function () use ($http, $escenarioProtegido): void {
    $e = $escenarioProtegido();
    $mismoDpi = $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', Escenario::json(Escenario::datosAna(['nombres' => 'Otra'])));
    igual([409, 'PACIENTE_EXISTENTE', 'RESERVADO'], [$mismoDpi->estado, $mismoDpi->cuerpo['error'] ?? null, $mismoDpi->cuerpo['codigo'] ?? null], 'mismo DPI');
    $parecida = Escenario::json(['nombres' => 'ana lucia', 'apellidos' => 'lopez perez', 'fecha_nacimiento' => '1985-03-14', 'sexo' => 'female']);
    $r = $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $parecida);
    igual([409, ['RESERVADO']], [$r->estado, $r->cuerpo['candidatos'] ?? null], 'coincidencia probable');
    igual('P-000001', $http($e, 'POST', '/api/v1/pacientes', 'token-supervisor-oriente', Escenario::json(Escenario::datosAna(['nombres' => 'Otra'])))->cuerpo['codigo'] ?? null, 'el supervisor sí ve el código');
    igual(2, count($e->repositorio->todos()), 'no se creó ningún duplicado');
});

prueba('PROT-06', 'Confidencialidad transversal: ninguna respuesta a admisión o clínico contiene el nombre ni el DPI del protegido', function () use ($http, $escenarioProtegido): void {
    $registro = new RegistroAccesoEnMemoria();
    $e = $escenarioProtegido($registro);
    $respuestas = [];
    foreach (['token-admision-oriente', 'token-clinico-oriente'] as $token) {
        $respuestas[] = $http($e, 'GET', '/api/v1/pacientes', $token, consulta: ['codigo' => 'P-000001']);
        $respuestas[] = $http($e, 'GET', '/api/v1/pacientes', $token, consulta: ['tipo_documento' => 'DPI', 'numero_documento' => Escenario::DPI_ANA]);
        $respuestas[] = $http($e, 'GET', '/api/v1/pacientes', $token, consulta: ['nombre' => 'ana']);
        $respuestas[] = $http($e, 'GET', '/api/v1/pacientes/P-000001', $token);
        $respuestas[] = $http($e, 'POST', '/api/v1/pacientes', $token, Escenario::json(Escenario::datosAna(['nombres' => 'Otra'])));
    }
    $todo = json_encode(array_map(static fn ($r) => [$r->cuerpo, $r->cabeceras], $respuestas), JSON_UNESCAPED_UNICODE) . json_encode($registro->lineas, JSON_UNESCAPED_UNICODE);
    foreach (['Ana Lucía', 'López', '1985-03-14', '5555 1234', '0101'] as $dato) {
        afirmar(!str_contains((string) $todo, $dato), "se filtró «{$dato}»");
    }
});
