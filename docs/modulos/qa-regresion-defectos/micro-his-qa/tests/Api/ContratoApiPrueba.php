<?php

declare(strict_types=1);

/*
 * Semana 5 — Conformidad entre el CONTRATO (api/openapi.yaml) y el CÓDIGO: si alguien cambia uno sin el otro, estas
 * pruebas fallan. El YAML se lee con un lector mínimo (sin dependencias) que conoce su indentación fija.
 */

use Qa\Presentacion\Http\Solicitud;

/** @return array<string, array{operationId: string, permiso: ?string, respuestas: list<string>}> «MÉTODO /ruta» → datos */
$apiContrato = static function (): array {
    $operaciones = [];
    $ruta = $metodo = null;
    $enRespuestas = false;
    foreach (file(dirname(__DIR__, 2) . '/api/openapi.yaml', FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
        if (preg_match('#^  (/api/\S+):$#', $linea, $m) === 1) {
            [$ruta, $metodo] = [$m[1], null];
        } elseif ($ruta !== null && preg_match('/^    (get|post|put|patch|delete):$/', $linea, $m) === 1) {
            $metodo = strtoupper($m[1]) . ' ' . $ruta;
            $operaciones[$metodo] = ['operationId' => '', 'permiso' => null, 'respuestas' => []];
            $enRespuestas = false;
        } elseif ($metodo !== null && preg_match('/^      operationId: (\S+)$/', $linea, $m) === 1) {
            $operaciones[$metodo]['operationId'] = $m[1];
        } elseif ($metodo !== null && preg_match('/^      x-permiso: (\S+)$/', $linea, $m) === 1) {
            $operaciones[$metodo]['permiso'] = $m[1];
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

/** @return list<string> los códigos de error del enum del esquema Error */
$apiErrores = static function (): array {
    $yaml = (string) file_get_contents(dirname(__DIR__, 2) . '/api/openapi.yaml');
    preg_match('/    Error:.*?error:\s*\n\s*type: string\s*\n\s*enum: \[([^\]]+)\]/s', $yaml, $m);

    return array_values(array_filter(array_map('trim', explode(',', str_replace("\n", ' ', $m[1] ?? '')))));
};

/** «MÉTODO /ruta» de cada ruta del código, con los parámetros como {nombre}. */
$apiCodigo = static function (): array {
    $rutas = (new ReflectionProperty(\Qa\Presentacion\Http\Kernel::class, 'rutas'))->getValue((new Escenario())->kernel());

    return array_map(static fn ($r): string => $r->metodo . ' ' . str_replace('\\', '', preg_replace(['/^#\^|\$#$/', '/\(\?<(\w+)>[^)]*\)/'], ['', '{$1}'], $r->patron)), $rutas);
};

prueba('API-01', 'Contrato = código: las 9 operaciones son las rutas del código, y cada x-permiso es la constante PERMISO de su caso de uso', function () use ($apiContrato, $apiCodigo): void {
    $contrato = $apiContrato();
    // En el contrato los parámetros se llaman casoId, campanaId…; en el código, {id}.
    $normalizar = static fn (string $k): string => preg_replace('/\{\w+\}/', '{id}', $k);
    $deContrato = array_map($normalizar, array_keys($contrato));
    $deCodigo = $apiCodigo();
    sort($deContrato);
    sort($deCodigo);
    igual($deCodigo, $deContrato, 'operaciones');
    $permisos = [];
    foreach ($contrato as $op) {
        $clase = 'Qa\\Aplicacion\\CasosDeUso\\' . ucfirst($op['operationId']);
        $permisos[$op['operationId']] = [class_exists($clase) ? constant("$clase::PERMISO") : 'sin caso de uso', $op['permiso']];
    }
    igual(array_map(static fn (array $p): string => $p[0], $permisos), array_map(static fn (array $p): ?string => $p[1], $permisos), 'x-permiso = PERMISO');
});

prueba('API-02', 'Cada operación documenta su éxito y los errores transversales (400, 401, 403, 500, 503)', function () use ($apiContrato): void {
    $faltan = [];
    foreach ($apiContrato() as $k => $op) {
        foreach (['400', '401', '403', '500', '503'] as $c) {
            if (!in_array($c, $op['respuestas'], true)) {
                $faltan[] = "$k $c";
            }
        }
        if (array_filter($op['respuestas'], static fn (string $c): bool => $c[0] === '2') === []) {
            $faltan[] = "$k sin 2xx";
        }
    }
    igual([], $faltan, 'faltan');
});

prueba('API-03', 'En escenarios reales por el kernel, cada estado está documentado en SU operación y cada código de error está en el enum', function () use ($apiContrato, $apiErrores): void {
    $contrato = $apiContrato();
    $errores = $apiErrores();
    $e = new Escenario();
    $k = $e->kernel();
    $png = base64_encode(Escenario::PNG);
    $pedir = static function (string $metodo, string $ruta, string $token, ?array $cuerpo = null, array $extra = []) use ($k): \Qa\Presentacion\Http\Respuesta {
        return $k->manejar(new Solicitud($metodo, $ruta, ['authorization' => "Bearer $token", 'x-tenant-id' => 'hosp-norte'] + $extra,
            $cuerpo === null ? '' : json_encode($cuerpo)));
    };
    $b = '/api/v1/qa';
    $res = static fn (string $clave): array => ['idempotency-key' => $clave];
    $escenarios = [
        ['POST', "$b/casos", 'token-ana-qa', ['id' => 'TC-300', 'titulo' => 'Nuevo', 'pasos' => [['accion' => 'a', 'esperado' => 'b']], 'prioridad' => 'ALTA', 'requisitos' => ['RF-01']]],
        ['POST', "$b/casos", 'token-ana-qa', ['id' => 'TC-300', 'titulo' => 'Nuevo', 'pasos' => [['accion' => 'a', 'esperado' => 'b']], 'prioridad' => 'ALTA', 'requisitos' => ['RF-01']]],
        ['POST', "$b/casos", 'token-ana-qa', ['id' => 'TC-301', 'titulo' => '', 'pasos' => [], 'prioridad' => 'X', 'requisitos' => []]],
        ['POST', "$b/casos", 'token-tito-tester', ['id' => 'TC-302', 'titulo' => 'x', 'pasos' => [], 'prioridad' => 'ALTA', 'requisitos' => []]],
        ['POST', "$b/casos/TC-300/aprobacion", 'token-lider-qa'],
        ['POST', "$b/casos/TC-300/aprobacion", 'token-lider-qa'],
        ['POST', "$b/casos/TC-999/aprobacion", 'token-lider-qa'],
        ['POST', "$b/campanas", 'token-lider-qa', ['nombre' => 'Incompleta', 'funcionalidades' => ['expediente'], 'casos' => ['TC-109']]],
        ['POST', "$b/campanas/1/inicio", 'token-lider-qa', ['build' => '1.4.0']],
        ['POST', "$b/campanas", 'token-lider-qa', ['nombre' => 'H4', 'funcionalidades' => ['expediente'], 'casos' => ['TC-107', 'TC-108']]],
        ['POST', "$b/campanas", 'token-lider-qa', ['nombre' => 'X', 'funcionalidades' => ['expediente'], 'casos' => ['TC-999']]],
        ['POST', "$b/campanas/2/inicio", 'token-lider-qa', ['build' => '1.4.0']],
        ['POST', "$b/campanas/2/inicio", 'token-lider-qa', ['build' => '1.4.0']],
        ['POST', "$b/campanas/2/inicio", 'token-lider-qa', ['build' => 7]],
        ['POST', "$b/campanas/2/resultados", 'token-tito-tester', ['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'contenido_base64' => $png]],
            'defecto' => ['titulo' => 'Nota editable', 'pasos' => ['Abrir'], 'severidad' => 'CRITICA']], $res('api-0000001')],
        ['POST', "$b/campanas/2/resultados", 'token-tito-tester', ['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'contenido_base64' => $png]],
            'defecto' => ['titulo' => 'Nota editable', 'pasos' => ['Abrir'], 'severidad' => 'CRITICA']], $res('api-0000001')],
        ['POST', "$b/campanas/2/resultados", 'token-tito-tester', ['caso' => 'TC-108', 'resultado' => 'PASA'], $res('api-0000001')],
        ['POST', "$b/campanas/2/resultados", 'token-tito-tester', ['caso' => 'TC-108', 'resultado' => 'BLOQUEADO'], $res('api-0000002')],
        ['POST', "$b/campanas/2/resultados", 'token-tito-tester', ['caso' => 'TC-108', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'contenido_base64' => base64_encode(str_repeat('x', 5 * 1024 * 1024 + 1))]]], $res('api-0000003')],
        ['POST', "$b/campanas/2/resultados", 'token-tito-tester', ['caso' => 'TC-108', 'resultado' => 'PASA']],
        ['POST', "$b/campanas/2/resultados", 'token-tito-tester', ['caso' => 'TC-101', 'resultado' => 'PASA'], $res('api-0000004')],
        ['POST', "$b/campanas/9/resultados", 'token-tito-tester', ['caso' => 'TC-101', 'resultado' => 'PASA'], $res('api-0000005')],
        ['POST', "$b/campanas/2/cierre", 'token-lider-qa'],
        ['POST', "$b/defectos/1/clasificacion", 'token-lider-qa', ['severidad' => 'MENOR', 'prioridad' => 'BAJA']],
        ['POST', "$b/defectos/1/clasificacion", 'token-lider-qa', ['severidad' => 'CRITICA', 'prioridad' => 'ALTA']],
        ['POST', "$b/defectos/1/correccion", 'token-dev-luis', ['build' => '1.4.1']],
        ['POST', "$b/defectos/1/correccion", 'token-dev-luis', ['build' => '1.4.1']],
        ['POST', "$b/campanas/2/resultados", 'token-tito-tester', ['caso' => 'TC-108', 'resultado' => 'PASA'], $res('api-0000006')],
        ['POST', "$b/campanas/2/cierre", 'token-lider-qa'],
        ['POST', "$b/campanas/2/cierre", 'token-lider-qa'],
        ['GET', "$b/campanas/2/trazabilidad", 'token-producto'],
        ['GET', "$b/campanas/2/trazabilidad", 'token-falso'],
        ['GET', "$b/campanas/2/trazabilidad", 'token-dev-luis', null, ['x-tenant-id' => 'hosp-sur']],
    ];
    $sinDocumentar = [];
    $codigos = [];
    foreach ($escenarios as $n => [$metodo, $ruta]) {
        $r = $pedir($metodo, $ruta, $escenarios[$n][2], $escenarios[$n][3] ?? null, $escenarios[$n][4] ?? []);
        $op = null;
        foreach (array_keys($contrato) as $clave) {
            [$m, $plantilla] = explode(' ', $clave, 2);
            if ($m === $metodo && preg_match('#^' . preg_replace('/\\\{\w+\\\}/', '[^/]+', preg_quote($plantilla, '#')) . '$#', $ruta)) {
                $op = $clave;
            }
        }
        if ($op === null || !in_array((string) $r->estado, $contrato[$op]['respuestas'], true)) {
            $sinDocumentar[] = "#$n $metodo $ruta → {$r->estado}";
        }
        if (isset($r->cuerpo['error']) && !in_array($r->cuerpo['error'], $errores, true)) {
            $sinDocumentar[] = "#$n código {$r->cuerpo['error']} fuera del enum";
        }
        afirmar(isset($r->cabeceras['X-Request-ID']), "#$n sin X-Request-ID");
        $codigos[$r->cuerpo['error'] ?? $r->cuerpo['codigo']] = true;
    }
    igual([], $sinDocumentar, 'sin documentar');
    afirmar(count($codigos) >= 25, 'los escenarios deben cubrir al menos 25 códigos distintos; cubren ' . count($codigos));
});
