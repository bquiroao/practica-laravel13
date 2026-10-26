<?php

declare(strict_types=1);

/*
 * Semana 5 — Conformidad entre el CONTRATO (api/openapi.yaml) y el CÓDIGO: si alguien cambia uno
 * sin el otro, estas pruebas fallan. El YAML se lee con un lector mínimo (sin dependencias) que
 * conoce la indentación fija del archivo.
 */

use Auditoria\Aplicacion\CasosDeUso\ConsultarMovimientos;
use Auditoria\Aplicacion\CasosDeUso\RegistrarMovimiento;
use Auditoria\Aplicacion\CasosDeUso\VerificarIntegridad;
use Auditoria\Presentacion\Http\Controladores\AuditoriaControlador;
use Auditoria\Presentacion\Http\Respuesta;
use Auditoria\Presentacion\Http\Rutas;
use Auditoria\Pruebas\Dobles\Escenario;

/** @return array<string, array{operationId: string, permiso: ?string, permisoRed: ?string, respuestas: list<string>}> «MÉTODO /ruta» → datos */
$apiContrato = static function (): array {
    $operaciones = [];
    $ruta = $metodo = null;
    $enRespuestas = false;
    foreach (file(dirname(__DIR__, 2) . '/api/openapi.yaml', FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
        if (preg_match('#^  (/api/\S+):$#', $linea, $m) === 1) {
            [$ruta, $metodo] = [$m[1], null];
        } elseif ($ruta !== null && preg_match('/^    (get|post|put|patch|delete):$/', $linea, $m) === 1) {
            $metodo = strtoupper($m[1]) . ' ' . $ruta;
            $operaciones[$metodo] = ['operationId' => '', 'permiso' => null, 'permisoRed' => null, 'respuestas' => []];
            $enRespuestas = false;
        } elseif ($metodo !== null && preg_match('/^      operationId: (\S+)$/', $linea, $m) === 1) {
            $operaciones[$metodo]['operationId'] = $m[1];
        } elseif ($metodo !== null && preg_match('/^      x-permiso: (\S+)$/', $linea, $m) === 1) {
            $operaciones[$metodo]['permiso'] = $m[1];
        } elseif ($metodo !== null && preg_match('/^      x-permiso-red: (\S+)$/', $linea, $m) === 1) {
            $operaciones[$metodo]['permisoRed'] = $m[1];
        } elseif ($metodo !== null && preg_match('/^      responses:$/', $linea) === 1) {
            $enRespuestas = true;
        } elseif ($enRespuestas && preg_match("/^        '(\\d{3})':$/", $linea, $m) === 1) {
            $operaciones[$metodo]['respuestas'][] = $m[1];
        } elseif (preg_match('/^\S/', $linea) === 1) {
            $ruta = $metodo = null;
        }
    }

    return $operaciones;
};

/** @return list<string> el enum de códigos de error del contrato */
$apiCodigos = static function (): array {
    $yaml = (string) file_get_contents(dirname(__DIR__, 2) . '/api/openapi.yaml');
    preg_match('/error:\s*\n\s*type: string\s*\n\s*enum: \[([^\]]+)\]/', $yaml, $m);

    return array_map('trim', explode(',', str_replace("\n", ' ', $m[1] ?? '')));
};

/** «MÉTODO /ruta» de cada ruta del código, con los parámetros como {nombre}. */
$apiCodigo = static function (): array {
    $c = Escenario::crear()->casos;
    $controlador = new AuditoriaControlador($c['registrar'], $c['consultar'], $c['detalle'], $c['verificar'], $c['ancla']);

    return array_map(static fn ($r): string => $r->metodo . ' ' . preg_replace(['/^#\^|\$#$/', '/\(\?<(\w+)>[^)]*\)/'], ['', '{$1}'], $r->patron),
        Rutas::definir($controlador));
};

prueba('API-01', 'Contrato = código: las mismas 5 operaciones; cada x-permiso es exactamente el que exige su caso de uso', function () use ($apiContrato, $apiCodigo): void {
    $contrato = $apiContrato();
    $codigo = $apiCodigo();
    $a = array_keys($contrato);
    sort($a);
    sort($codigo);
    igual($codigo, $a, 'operaciones');
    $esperado = [
        'registrarMovimiento' => [RegistrarMovimiento::PERMISO, null],
        'consultarMovimientos' => [ConsultarMovimientos::PERMISO, ConsultarMovimientos::PERMISO_RED],
        'verDetalleMovimiento' => [ConsultarMovimientos::PERMISO, ConsultarMovimientos::PERMISO_RED],
        'verificarIntegridad' => [VerificarIntegridad::PERMISO, null],
        'obtenerAncla' => [VerificarIntegridad::PERMISO, null],
    ];
    foreach ($contrato as $operacion => $datos) {
        igual($esperado[$datos['operationId']] ?? 'operationId desconocido', [$datos['permiso'], $datos['permisoRed']], "permisos de $operacion");
    }
});

prueba('API-02', 'Contrato completo: toda operación documenta 400/401/403/500/503; el registro 201, 200, 409 y 422; la consulta 422; el detalle 404 y 405', function () use ($apiContrato): void {
    $contrato = $apiContrato();
    foreach ($contrato as $operacion => $datos) {
        foreach (['400', '401', '403', '500', '503'] as $codigo) {
            afirmar(in_array($codigo, $datos['respuestas'], true), "$operacion no documenta $codigo");
        }
    }
    $exigidas = ['POST /api/v1/auditoria/movimientos' => ['201', '200', '409', '422'], 'GET /api/v1/auditoria/movimientos' => ['200', '422'],
        'GET /api/v1/auditoria/movimientos/{id}' => ['200', '404', '405']];
    foreach ($exigidas as $operacion => $codigos) {
        foreach ($codigos as $codigo) {
            afirmar(in_array($codigo, $contrato[$operacion]['respuestas'], true), "$operacion no documenta $codigo");
        }
    }
});

prueba('API-03', 'Cada estado y cada código de error que la API devuelve en 24 escenarios reales está documentado en el contrato', function () use ($apiContrato, $apiCodigos): void {
    $contrato = $apiContrato();
    $codigos = $apiCodigos();
    $e = Escenario::crear();
    $mov = '/api/v1/auditoria/movimientos';
    $hoy = ['desde' => '2026-10-21', 'hasta' => '2026-10-21'];
    $p = static fn (string $metodo, string $ruta, ?string $token, string $hospital = Escenario::H_ORIENTE, ?array $cuerpo = null, array $q = []): Respuesta =>
        $e->kernel->manejar(Escenario::http($metodo, $ruta, $token, $hospital, $cuerpo, $q));
    $casos = [
        ['POST /api/v1/auditoria/movimientos', $p('POST', $mov, 'token-svc-pacientes-oriente', cuerpo: Escenario::mov())],
        ['POST /api/v1/auditoria/movimientos', $p('POST', $mov, 'token-svc-pacientes-oriente', cuerpo: Escenario::mov())],
        ['POST /api/v1/auditoria/movimientos', $p('POST', $mov, 'token-svc-pacientes-oriente', cuerpo: Escenario::mov(['resultado' => 'error']))],
        ['POST /api/v1/auditoria/movimientos', $p('POST', $mov, 'token-svc-pacientes-oriente', cuerpo: Escenario::mov(['accion' => 'X']))],
        ['POST /api/v1/auditoria/movimientos', $p('POST', $mov, 'token-svc-pacientes-oriente', cuerpo: Escenario::mov(['modulo' => 'rbac']))],
        ['POST /api/v1/auditoria/movimientos', $p('POST', $mov, 'token-svc-pacientes-oriente')],
        ['POST /api/v1/auditoria/movimientos', $p('POST', $mov, 'token-auditor-oriente', cuerpo: Escenario::mov(['event_id' => 'z']))],
        ['POST /api/v1/auditoria/movimientos', $p('POST', $mov, null)],
        ['POST /api/v1/auditoria/movimientos', $p('POST', $mov, 'token-svc-pacientes-oriente', '')],
        ['POST /api/v1/auditoria/movimientos', $p('POST', $mov, 'token-svc-pacientes-oriente', Escenario::H_NORTE, Escenario::mov())],
        ['GET /api/v1/auditoria/movimientos', $p('GET', $mov, 'token-auditor-oriente', q: $hoy)],
        ['GET /api/v1/auditoria/movimientos', $p('GET', $mov, 'token-auditor-oriente', q: [])],
        ['GET /api/v1/auditoria/movimientos', $p('GET', $mov, 'token-auditor-oriente', q: $hoy + ['ip' => '1'])],
        ['GET /api/v1/auditoria/movimientos', $p('GET', $mov, 'token-auditor-oriente', q: $hoy + ['usuario' => ['a']])],
        ['GET /api/v1/auditoria/movimientos', $p('GET', $mov, 'token-svc-pacientes-oriente', q: $hoy)],
        ['GET /api/v1/auditoria/movimientos/{id}', $p('GET', "$mov/1", 'token-auditor-oriente')],
        ['GET /api/v1/auditoria/movimientos/{id}', $p('GET', "$mov/999", 'token-auditor-oriente')],
        ['GET /api/v1/auditoria/movimientos/{id}', $p('DELETE', "$mov/1", 'token-superadmin', '*')],
        ['POST /api/v1/auditoria/verificaciones', $p('POST', '/api/v1/auditoria/verificaciones', 'token-superadmin', '*', ['hospital' => Escenario::H_ORIENTE])],
        ['POST /api/v1/auditoria/verificaciones', $p('POST', '/api/v1/auditoria/verificaciones', 'token-superadmin', '*', ['ancla' => ['secuencia' => 'x']])],
        ['POST /api/v1/auditoria/verificaciones', $p('POST', '/api/v1/auditoria/verificaciones', 'token-auditor-oriente', cuerpo: [])],
        ['GET /api/v1/auditoria/anclas', $p('GET', '/api/v1/auditoria/anclas', 'token-superadmin', '*', q: ['hospital' => Escenario::H_ORIENTE])],
        ['GET /api/v1/auditoria/anclas', $p('GET', '/api/v1/auditoria/anclas', 'token-auditor-oriente', q: ['hospital' => Escenario::H_ORIENTE])],
    ];
    $e->bitacora->fallarEn = ['buscar'];
    $casos[] = ['GET /api/v1/auditoria/movimientos', $p('GET', $mov, 'token-auditor-oriente', q: $hoy)];
    igual(24, count($casos), 'escenarios');
    $vistos = [];
    foreach ($casos as [$operacion, $r]) {
        afirmar(in_array((string) $r->estado, $contrato[$operacion]['respuestas'], true), "$operacion devolvió {$r->estado}, que el contrato no documenta");
        if (isset($r->cuerpo['error'])) {
            afirmar(in_array($r->cuerpo['error'], $codigos, true), "el código {$r->cuerpo['error']} no está en el contrato");
            $vistos[$r->cuerpo['error']] = true;
        }
    }
    afirmar(count($vistos) >= 12, 'los escenarios cubren pocos códigos de error: ' . implode(', ', array_keys($vistos)));
});
