<?php

declare(strict_types=1);

/*
 * Semana 5 — Conformidad entre el CONTRATO (api/openapi.yaml) y el CÓDIGO: si alguien cambia uno sin el otro,
 * estas pruebas fallan. El YAML se lee con un lector mínimo (sin dependencias) que conoce su indentación fija.
 */

use Prescripciones\Aplicacion\CasosDeUso\ConsultarPrescripcion;
use Prescripciones\Aplicacion\CasosDeUso\EmitirPrescripcion;
use Prescripciones\Aplicacion\CasosDeUso\VerificarAlergiasPrevia;
use Prescripciones\Presentacion\Http\Controladores\PrescripcionesControlador;
use Prescripciones\Presentacion\Http\Rutas;
use Prescripciones\Presentacion\Http\Solicitud;

/** @return array<string, array{operationId: string, permiso: ?string, advertencia: ?string, respuestas: list<string>}> «MÉTODO /ruta» → datos */
$apiContrato = static function (): array {
    $operaciones = [];
    $ruta = $metodo = null;
    $enRespuestas = false;
    foreach (file(dirname(__DIR__, 2) . '/api/openapi.yaml', FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
        if (preg_match('#^  (/api/\S+):$#', $linea, $m) === 1) {
            [$ruta, $metodo] = [$m[1], null];
        } elseif ($ruta !== null && preg_match('/^    (get|post|put|patch|delete):$/', $linea, $m) === 1) {
            $metodo = strtoupper($m[1]) . ' ' . $ruta;
            $operaciones[$metodo] = ['operationId' => '', 'permiso' => null, 'advertencia' => null, 'respuestas' => []];
            $enRespuestas = false;
        } elseif ($metodo !== null && preg_match('/^      operationId: (\S+)$/', $linea, $m) === 1) {
            $operaciones[$metodo]['operationId'] = $m[1];
        } elseif ($metodo !== null && preg_match('/^      x-permiso: (\S+)$/', $linea, $m) === 1) {
            $operaciones[$metodo]['permiso'] = $m[1];
        } elseif ($metodo !== null && preg_match('/^      x-permiso-advertencia: (\S+)$/', $linea, $m) === 1) {
            $operaciones[$metodo]['advertencia'] = $m[1];
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

/** @return list<string> los valores de un enum del contrato, ubicado por el nombre de su propiedad */
$apiEnum = static function (string $propiedad): array {
    $yaml = (string) file_get_contents(dirname(__DIR__, 2) . '/api/openapi.yaml');
    preg_match('/' . $propiedad . ':\s*\n\s*type: string\s*\n\s*enum: \[([^\]]+)\]/', $yaml, $m);

    return array_values(array_filter(array_map('trim', explode(',', str_replace("\n", ' ', $m[1] ?? '')))));
};

/** «MÉTODO /ruta» de cada ruta del código, con los parámetros como {nombre}. */
$apiCodigo = static function (): array {
    $c = (new Escenario())->casos;

    return array_map(static fn ($r): string => $r->metodo . ' ' . preg_replace(['/^#\^|\$#$/', '/\(\?<(\w+)>[^)]*\)/'], ['', '{$1}'], $r->patron),
        Rutas::definir(new PrescripcionesControlador($c['previa'], $c['emitir'], $c['consultar'])));
};

prueba('API-01', 'Contrato = código: las mismas 3 operaciones, y cada x-permiso es la constante del caso de uso', function () use ($apiContrato, $apiCodigo): void {
    $contrato = $apiContrato();
    $enContrato = array_keys($contrato);
    $enCodigo = $apiCodigo();
    sort($enContrato);
    sort($enCodigo);
    igual($enCodigo, $enContrato, 'operaciones');
    $esperado = [
        'verificarAlergias' => [VerificarAlergiasPrevia::PERMISO, null],
        'emitirPrescripcion' => [EmitirPrescripcion::PERMISO, EmitirPrescripcion::PERMISO_OVERRIDE],
        'consultarPrescripcion' => [ConsultarPrescripcion::PERMISO, null],
    ];
    foreach ($contrato as $operacion => $datos) {
        igual($esperado[$datos['operationId']] ?? null, [$datos['permiso'], $datos['advertencia']], "permisos de $operacion");
    }
});

prueba('API-02', 'Cada operación documenta los errores transversales (400, 401, 403, 404, 500, 503) y su respuesta de éxito', function () use ($apiContrato): void {
    $exito = ['verificarAlergias' => ['200'], 'emitirPrescripcion' => ['201', '200'], 'consultarPrescripcion' => ['200']];
    foreach ($apiContrato() as $operacion => $datos) {
        $faltan = array_diff([...$exito[$datos['operationId']], '400', '401', '403', '404', '500', '503'], $datos['respuestas']);
        igual([], array_values($faltan), "faltan en $operacion");
    }
});

prueba('API-03', 'En 24 escenarios reales por el kernel, cada estado y cada código que sale está documentado en el contrato',
    function () use ($apiContrato, $apiEnum): void {
        $contrato = $apiContrato();
        $errores = $apiEnum('error');
        // Los «codigo» de éxito están en enums de una línea: {type: string, enum: [...]}.
        preg_match_all('/codigo: \{type: string, enum: \[([^\]]+)\]\}/', (string) file_get_contents(dirname(__DIR__, 2) . '/api/openapi.yaml'), $m);
        $codigosExito = array_map('trim', explode(',', implode(',', $m[1])));
        $e = new Escenario();
        $k = $e->kernel();
        $item = static fn (string $m, float|int $d = 500, int $f = 8): array => Escenario::item($m, $d, $f);
        $just = ['justificacion' => 'Sin alternativa: cultivo sensible solo a cefalosporinas'];
        $emitir = static fn (array $cuerpo, string $clave, string $token = 'token-dr-lopez', array $extra = []): array
            => ['POST', '/api/v1/prescripciones', $cuerpo, $token, 'hosp-norte', ['idempotency-key' => $clave] + $extra];
        $escenarios = [
            ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-001', 'items' => [$item('AMOX500')]], 'token-dr-lopez', 'hosp-norte', []],
            ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-003', 'items' => [$item('AMOX500')]], 'token-dr-lopez', 'hosp-norte', []],
            ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-005', 'items' => [$item('PARA500')]], 'token-dr-lopez', 'hosp-norte', []],
            ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-005', 'items' => [$item('PARA500', 1000, 4)]], 'token-dr-lopez', 'hosp-norte', []],
            ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-900', 'items' => [$item('PARA500')]], 'token-dr-lopez', 'hosp-norte', []],
            $emitir(['paciente' => 'P-005', 'items' => [$item('PARA500')]], 'a1'),
            $emitir(['paciente' => 'P-005', 'items' => [$item('PARA500')]], 'a1'),
            $emitir(['paciente' => 'P-005', 'items' => [$item('IBU400', 400)]], 'a1'),
            $emitir(['paciente' => 'P-001', 'items' => [$item('AMOX500')]] + $just, 'a2'),
            $emitir(['paciente' => 'P-004', 'items' => [$item('IBU400', 400)]] + $just, 'a3'),
            $emitir(['paciente' => 'P-002', 'items' => [$item('CEFA500')]], 'a4'),
            $emitir(['paciente' => 'P-002', 'items' => [$item('CEFA500')]] + $just, 'a5'),
            $emitir(['paciente' => 'P-003', 'items' => [$item('AMOX500')]], 'a6'),
            $emitir(['paciente' => 'P-005', 'items' => [$item('RANI150')]], 'a7'),
            $emitir(['paciente' => 'P-005', 'items' => [$item('PARA500', 1000, 4)]], 'a8'),
            $emitir(['paciente' => 'P-005', 'items' => [$item('PARA500')]], 'a9', 'token-farmacia-norte'),
            $emitir(['paciente' => 'P-900', 'items' => [$item('PARA500')]], 'a10'),
            ['POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => []], 'token-dr-lopez', 'hosp-norte', []],
            ['GET', '/api/v1/prescripciones/1', null, 'token-farmacia-norte', 'hosp-norte', []],
            ['GET', '/api/v1/prescripciones/999', null, 'token-farmacia-norte', 'hosp-norte', []],
            ['GET', '/api/v1/prescripciones/1', null, 'token-falso', 'hosp-norte', []],
            ['GET', '/api/v1/prescripciones/1', null, 'token-farmacia-norte', null, []],
            ['GET', '/api/v1/prescripciones/1', null, 'token-farmacia-norte', 'hosp-sur', []],
            ['DELETE', '/api/v1/prescripciones/1', null, 'token-farmacia-norte', 'hosp-norte', []],
        ];
        $vistos = [];
        foreach ($escenarios as $i => [$metodo, $ruta, $cuerpo, $token, $tenant, $extra]) {
            $r = $k->manejar(Escenario::http($metodo, $ruta, $cuerpo, $token, $tenant, $extra));
            $operacion = $metodo === 'DELETE' ? 'GET /api/v1/prescripciones/{id}' : $metodo . ' ' . preg_replace('#/\d+$#', '/{id}', $ruta);
            afirmar(in_array((string) $r->estado, $contrato[$operacion]['respuestas'], true), "escenario $i: $operacion no documenta {$r->estado}");
            $codigo = $r->cuerpo['error'] ?? $r->cuerpo['codigo'] ?? null;
            afirmar(in_array($codigo, $r->estado >= 400 ? $errores : $codigosExito, true), "escenario $i: código «{$codigo}» no documentado");
            afirmar(isset($r->cabeceras['X-Request-ID']), "escenario $i: sin X-Request-ID");
            $vistos[$codigo] = true;
        }
        afirmar(count($vistos) >= 15, 'se esperaban al menos 15 códigos distintos; se vieron ' . count($vistos) . ': ' . implode(', ', array_keys($vistos)));
    });
