<?php

declare(strict_types=1);

/*
 * Semana 5 — Conformidad entre el CONTRATO (api/openapi.yaml) y el CÓDIGO. El YAML se lee con un lector mínimo
 * (sin dependencias) que conoce su indentación fija; si alguien cambia uno sin el otro, estas pruebas fallan.
 */

use E2e\Aplicacion\CasosDeUso;
use E2e\Presentacion\Http\Controladores\Rutas;
use E2e\Presentacion\Http\Solicitud;

/** @return array<string, array{operationId: string, permiso: ?string, respuestas: list<string>}> «MÉTODO /ruta» → datos */
$apiContrato = static function (): array {
    $operaciones = [];
    $ruta = $clave = null;
    $enRespuestas = false;
    foreach (file(dirname(__DIR__, 2) . '/api/openapi.yaml', FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
        if (preg_match('#^  (/api/\S+):$#', $linea, $m) === 1) {
            [$ruta, $clave] = [$m[1], null];
        } elseif ($ruta !== null && preg_match('/^    (get|post|put|patch|delete):$/', $linea, $m) === 1) {
            $clave = strtoupper($m[1]) . ' ' . $ruta;
            $operaciones[$clave] = ['operationId' => '', 'permiso' => null, 'respuestas' => []];
            $enRespuestas = false;
        } elseif ($clave !== null && preg_match('/^      operationId: (\S+)$/', $linea, $m) === 1) {
            $operaciones[$clave]['operationId'] = $m[1];
        } elseif ($clave !== null && preg_match('/^      x-permiso: (\S+)$/', $linea, $m) === 1) {
            $operaciones[$clave]['permiso'] = $m[1];
        } elseif ($clave !== null && preg_match('/^      responses:$/', $linea) === 1) {
            $enRespuestas = true;
        } elseif ($enRespuestas && preg_match("/^        '(\\d{3})':$/", $linea, $m) === 1) {
            $operaciones[$clave]['respuestas'][] = $m[1];
        } elseif (preg_match('/^\S/', $linea) === 1) {
            $ruta = $clave = null;
        }
    }

    return $operaciones;
};
/** @return list<string> */
$apiErrores = static function (): array {
    preg_match('/    Error:.*?error:\s*\n\s*type: string\s*\n\s*enum: \[([^\]]+)\]/s', (string) file_get_contents(dirname(__DIR__, 2) . '/api/openapi.yaml'), $m);

    return array_values(array_filter(array_map('trim', explode(',', str_replace("\n", ' ', $m[1] ?? '')))));
};
$normalizar = static fn (string $ruta): string => preg_replace(['/\{\w+\}/', '/\(\?<\w+>(?:[^()]|\((?:[^()])*\))*\)/'], '{p}', $ruta);
$casoDeUso = ['registrarEscenario' => CasosDeUso\RegistrarEscenario::class, 'registrarVersion' => CasosDeUso\RegistrarVersion::class,
    'planificarPruebas' => CasosDeUso\PlanificarPruebas::class, 'iniciarEjecucion' => CasosDeUso\IniciarEjecucion::class,
    'evaluarGate' => CasosDeUso\EvaluarGate::class, 'consultarTrazabilidad' => CasosDeUso\ConsultarTrazabilidad::class,
    'reportarResultado' => CasosDeUso\ReportarResultado::class, 'cerrarEjecucion' => CasosDeUso\CerrarEjecucion::class];

prueba('API-01', 'Contrato = código: las 8 operaciones son las 8 rutas del código, y cada x-permiso es la constante PERMISO de su caso de uso', function () use ($apiContrato, $normalizar, $casoDeUso): void {
    $contrato = $apiContrato();
    $deContrato = array_map($normalizar, array_keys($contrato));
    $deCodigo = array_map(static fn (array $r): string => $r[0] . ' ' . $normalizar(Rutas::BASE . $r[1]), Rutas::tabla());
    sort($deContrato);
    sort($deCodigo);
    igual($deCodigo, $deContrato, 'operaciones');
    foreach ($contrato as $op => $d) {
        afirmar(isset($casoDeUso[$d['operationId']]), "$op: operationId sin caso de uso");
        igual($casoDeUso[$d['operationId']]::PERMISO, $d['permiso'], "$op: x-permiso");
    }
});

prueba('API-02', 'Cada operación documenta su éxito y los errores transversales (400, 401, 403, 500, 503); no hay PUT ni DELETE', function () use ($apiContrato): void {
    foreach ($apiContrato() as $op => $d) {
        igual([], array_values(array_diff(['400', '401', '403', '500', '503'], $d['respuestas'])), "$op: transversales");
        afirmar(array_intersect(['200', '201'], $d['respuestas']) !== [], "$op: sin respuesta de éxito");
        afirmar(!str_starts_with($op, 'PUT') && !str_starts_with($op, 'DELETE'), "$op: el contrato no tiene ediciones ni borrados");
    }
});

prueba('API-03', 'En escenarios reales por el kernel, cada estado está documentado en SU operación y cada código de error está en el enum', function () use ($apiContrato, $apiErrores, $normalizar): void {
    $contrato = [];
    foreach ($apiContrato() as $op => $d) {
        $contrato[$normalizar($op)] = $d['respuestas'];
    }
    $enum = $apiErrores();
    $e = new Escenario();
    $k = $e->kernel();
    $vistos = [];
    $pedir = static function (string $metodo, string $ruta, string $token = 'token-pipeline', $cuerpo = null, array $extra = []) use ($k, $normalizar, &$vistos): void {
        $r = $k->manejar(new Solicitud($metodo, $ruta, $extra + ['authorization' => "Bearer $token", 'x-tenant-id' => 'hosp-norte'],
            is_string($cuerpo) ? $cuerpo : ($cuerpo === null ? '' : json_encode($cuerpo))));
        $vistos[] = [$metodo . ' ' . $normalizar(preg_replace('#/(\d+\.\d+\.\d+|\d+)(?=/|$)#', '/{v}', $ruta)), (string) $r->estado, $r->cuerpo['error'] ?? null];
    };
    $id = $e->ejecucionEnCurso();
    $ok = ['escenario' => 'E2E-101', 'resultado' => 'PASA', 'intentos' => 1];
    $pedir('POST', '/api/v1/e2e/escenarios', 'token-ing-qa', ['id' => 'E2E-300', 'titulo' => 'x', 'modulo' => 'citas', 'critico' => false, 'requisitos' => ['RF-01'], 'datos' => 'real']);
    $pedir('POST', '/api/v1/e2e/escenarios', 'token-ing-qa', ['id' => 'E2E-101', 'titulo' => 'x', 'modulo' => 'citas', 'critico' => false, 'requisitos' => ['RF-01'], 'datos' => 'SINT-AB']);
    $pedir('POST', '/api/v1/e2e/versiones', cuerpo: ['numero' => '2.4.0', 'commit' => 'c56f169', 'digest' => Escenario::DIGEST, 'modulos' => ['citas']]);
    $pedir('POST', '/api/v1/e2e/versiones', cuerpo: '{"numero":');
    $pedir('POST', '/api/v1/e2e/versiones/2.4.0/plan', 'token-ing-qa', ['escenarios' => ['E2E-101']]);
    $pedir('POST', '/api/v1/e2e/versiones/9.9.9/plan', 'token-ing-qa', ['escenarios' => []]);
    $pedir('POST', '/api/v1/e2e/versiones/2.4.0/ejecuciones', cuerpo: ['digest' => 'sha256:' . str_repeat('0', 64), 'entorno' => 'staging']);
    $pedir('POST', "/api/v1/e2e/ejecuciones/$id/resultados", cuerpo: $ok, extra: ['idempotency-key' => 'api-03-clave-1']);
    $pedir('POST', "/api/v1/e2e/ejecuciones/$id/resultados", cuerpo: $ok, extra: ['idempotency-key' => 'api-03-clave-1']);
    $pedir('POST', "/api/v1/e2e/ejecuciones/$id/resultados", cuerpo: $ok);
    $pedir('POST', "/api/v1/e2e/ejecuciones/$id/resultados", cuerpo: ['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1,
        'evidencias' => [['tipo' => 'video', 'contenido_base64' => base64_encode(str_repeat('v', 20 * 1024 * 1024 + 1))]]], extra: ['idempotency-key' => 'api-03-clave-2']);
    $pedir('POST', "/api/v1/e2e/ejecuciones/$id/resultados", cuerpo: ['escenario' => 'E2E-104', 'resultado' => 'QUIZAS', 'intentos' => 1], extra: ['idempotency-key' => 'api-03-clave-3']);
    $pedir('POST', "/api/v1/e2e/ejecuciones/$id/cierre", cuerpo: []);
    $pedir('POST', '/api/v1/e2e/versiones/2.4.0/gate', 'token-release', ['ejecucion' => $id]);
    $pedir('POST', '/api/v1/e2e/versiones/2.4.0/gate', cuerpo: ['ejecucion' => $id]);
    $pedir('GET', '/api/v1/e2e/versiones/2.4.0/trazabilidad', 'token-dev');
    $e->almacen->caido = true;
    $pedir('POST', "/api/v1/e2e/ejecuciones/$id/resultados", cuerpo: ['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1,
        'evidencias' => [['tipo' => 'log', 'contenido_base64' => base64_encode('500')]]], extra: ['idempotency-key' => 'api-03-clave-4']);
    $e->almacen->caido = false;
    $e->completar($id, ['E2E-101' => ['PASA', 1]]);
    $pedir('POST', '/api/v1/e2e/versiones/2.4.0/gate', 'token-release', ['ejecucion' => $id]);
    $problemas = [];
    foreach ($vistos as [$op, $estado, $error]) {
        $op = preg_replace('#\{v\}#', '{p}', $op);
        if (!in_array($estado, $contrato[$op] ?? [], true)) {
            $problemas[] = "$op respondió $estado y no está documentado";
        }
        if ($error !== null && !in_array($error, $enum, true)) {
            $problemas[] = "$op devolvió el error $error, que no está en el enum";
        }
    }
    igual([], $problemas, 'contrato frente a respuestas reales');
    afirmar(count(array_unique(array_column($vistos, 1))) >= 9, 'el recorrido no cubrió suficientes estados distintos');
});

prueba('API-04', 'Estructura del contrato sin dependencias: cada $ref existe, cada {parámetro} de ruta está declarado, operationId únicos, sin tabulaciones', function () use ($apiContrato): void {
    $yaml = (string) file_get_contents(dirname(__DIR__, 2) . '/api/openapi.yaml');
    $problemas = [];
    afirmar(!str_contains($yaml, "\t"), 'hay tabulaciones');
    preg_match_all('/\$ref: \'#\/components\/(\w+)\/(\w+)\'/', $yaml, $refs, PREG_SET_ORDER);
    afirmar(count($refs) > 50, 'no se leyeron las referencias');
    foreach ($refs as [, $seccion, $nombre]) {
        if (preg_match("/^  $seccion:\n(?:(?:    .*|\s*)\n)*?    $nombre:\n/m", $yaml) !== 1) {
            $problemas[] = "\$ref a components/$seccion/$nombre que no existe";
        }
    }
    $parametros = ['version' => 'Version', 'ejecucionId' => 'EjecucionId'];
    foreach (array_keys($apiContrato()) as $op) {
        preg_match_all('/\{(\w+)\}/', $op, $m);
        foreach ($m[1] as $p) {
            if (!isset($parametros[$p])) {
                $problemas[] = "$op: el parámetro {$p} no está declarado";
            }
        }
    }
    $ids = array_column($apiContrato(), 'operationId');
    igual(count($ids), count(array_unique($ids)), 'operationId repetidos');
    igual([], $problemas, 'estructura');
});
