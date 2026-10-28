<?php

declare(strict_types=1);

/*
 * Semana 12 — Cambio solicitado «¿Quién vio la ficha de este paciente?»: filtrar por RECURSO.
 * Escritas ANTES de implementar (rojo → verde). Es una extensión OCP: un filtro nuevo, sin tocar
 * el caso de uso, con el mismo alcance de tenant y la misma meta-auditoría.
 */

use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Pruebas\Dobles\Escenario;

const REC_DIA = ['desde' => '2026-10-21', 'hasta' => '2026-10-21'];

/** Tres movimientos sobre P-000001 (uno en Norte), uno sobre P-000002 y uno sin recurso. */
$recSembrar = static function (): object {
    $e = Escenario::crear();
    foreach ([['r-1', 'u-ana', 'paciente:P-000001', '08:00'], ['r-2', 'u-luis', 'paciente:P-000002', '08:30'], ['r-3', 'u-marta', 'paciente:P-000001', '09:00'],
        ['r-4', 'u-jose', null, '09:30']] as [$evento, $usuario, $recurso, $hora]) {
        Escenario::registrar($e, Escenario::mov(['event_id' => $evento, 'usuario' => $usuario, 'recurso' => $recurso, 'ocurrido_en' => "2026-10-21T$hora:00-06:00"]));
    }
    Escenario::registrar($e, Escenario::mov(['event_id' => 'r-n', 'hospital' => Escenario::H_NORTE, 'usuario' => 'u-rosa', 'recurso' => 'paciente:P-000001',
        'ocurrido_en' => '2026-10-21T10:00:00-06:00']), 'svc-pacientes', Escenario::H_NORTE);

    return $e;
};
$recUsuarios = static fn (Resultado $r): array => array_map(static fn (array $m): string => $m['usuario'], $r->datos['movimientos'] ?? []);

prueba('REC-01', '¿Quién vio la ficha P-000001? Solo los movimientos de ese recurso, del hospital, más recientes primero; la consulta se audita con el filtro', function () use ($recSembrar, $recUsuarios): void {
    $e = $recSembrar();
    $r = Escenario::consultar($e, REC_DIA + ['recurso' => 'paciente:P-000001']);
    igual([Resultado::OK, ['u-marta', 'u-ana']], [$r->estado, $recUsuarios($r)], 'solo P-000001 de Oriente');
    $meta = $e->bitacora->todos()[count($e->bitacora->todos()) - 1];
    igual(['AUDITORIA_CONSULTADA', REC_DIA + ['recurso' => 'paciente:P-000001']], [$meta->accion, $meta->detalle['filtros']], 'la evidencia guarda el recurso buscado');
    igual(['u-marta', 'u-ana'], $recUsuarios(Escenario::consultar($e, REC_DIA + ['recurso' => '  paciente:P-000001 '])), 'espacios alrededor: mismo resultado');
});

prueba('REC-02', 'Tenant: el mismo recurso en Norte no aparece para Oriente; la red ve los tres', function () use ($recSembrar, $recUsuarios): void {
    $e = $recSembrar();
    igual(['u-marta', 'u-ana'], $recUsuarios(Escenario::consultar($e, REC_DIA + ['recurso' => 'paciente:P-000001'])), 'Oriente');
    igual(['u-rosa', 'u-marta', 'u-ana'], $recUsuarios(Escenario::consultar($e, REC_DIA + ['recurso' => 'paciente:P-000001'], 'superadmin', '*')), 'red');
});

prueba('REC-03', 'Validación: vacío o más de 128 caracteres → 422 en «recurso»; 128 se acepta (el mismo máximo que al registrar)', function () use ($recSembrar): void {
    $e = $recSembrar();
    foreach (['', '   ', str_repeat('x', 129)] as $valor) {
        $r = Escenario::consultar($e, REC_DIA + ['recurso' => $valor]);
        igual([Resultado::INVALIDO, 'DATOS_INVALIDOS', ['recurso' => 'De 1 a 128 caracteres.']], [$r->estado, $r->codigo, $r->datos['campos'] ?? null],
            'recurso de ' . strlen($valor) . ' caracteres');
    }
    igual(Resultado::OK, Escenario::consultar($e, REC_DIA + ['recurso' => str_repeat('x', 128)])->estado, '128 caracteres');
    igual(['usuario' => 'De 1 a 64 caracteres.'], Escenario::consultar($e, REC_DIA + ['usuario' => str_repeat('x', 65)])->datos['campos'] ?? null,
        'los demás filtros conservan su máximo de 64');
});

prueba('REC-04', 'Contrato = código: los parámetros de consulta del OpenAPI son exactamente los que reclaman los filtros, más «cursor»', function (): void {
    $yaml = (string) file_get_contents(dirname(__DIR__, 2) . '/api/openapi.yaml');
    preg_match('#\n  /api/v1/auditoria/movimientos:\n.*?\n    get:\n(.*?)\n    [a-z]+:\n|\n  /api/v1/auditoria/movimientos:\n.*?\n    get:\n(.*?)\n  /#s', $yaml, $m);
    preg_match_all('/- \{name: ([a-z_]+), in: query/', ($m[1] ?? '') . ($m[2] ?? ''), $documentados);
    $reclamados = array_merge(...array_map(static fn ($f): array => $f->parametros(), \filtrosDeConsulta(Escenario::config())));
    $esperados = [...$reclamados, 'cursor'];
    sort($esperados);
    $doc = $documentados[1];
    sort($doc);
    igual($esperados, $doc, 'parámetros de GET /movimientos');
});
