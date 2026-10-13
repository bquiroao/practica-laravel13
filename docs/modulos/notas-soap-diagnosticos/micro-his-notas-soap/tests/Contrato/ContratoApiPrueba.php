<?php

declare(strict_types=1);

/*
 * Contrato API: api/openapi.yaml y el código deben decir lo mismo. Si alguien agrega una ruta,
 * cambia un permiso, devuelve un código HTTP o un código de error no documentado, estas pruebas fallan.
 * El lector de YAML es mínimo a propósito: entiende el formato controlado de este archivo.
 */

use NotasSoap\Pruebas\Dobles\Escenario;

/** @return array<string, array{permiso: ?string, respuestas: list<string>}> clave "METODO /ruta" */
function operacionesDelContrato(): array
{
    $operaciones = [];
    $ruta = null;
    $clave = null;
    $enRespuestas = false;
    foreach (file(dirname(__DIR__, 2) . '/api/openapi.yaml', FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
        if (str_starts_with($linea, 'components:')) {
            break;
        }
        if (preg_match('#^  (/\S+):$#', $linea, $m) === 1) {
            $ruta = $m[1];
        } elseif ($ruta !== null && preg_match('/^    (get|post|put|patch|delete):$/', $linea, $m) === 1) {
            $clave = strtoupper($m[1]) . ' ' . $ruta;
            $operaciones[$clave] = ['permiso' => null, 'respuestas' => []];
            $enRespuestas = false;
        } elseif ($clave !== null && preg_match('/^      x-permiso: (\S+)$/', $linea, $m) === 1) {
            $operaciones[$clave]['permiso'] = $m[1];
        } elseif ($clave !== null && preg_match('/^      (\S+):/', $linea, $m) === 1) {
            $enRespuestas = $m[1] === 'responses';
        } elseif ($clave !== null && $enRespuestas && preg_match("/^        '(\d{3})':$/", $linea, $m) === 1) {
            $operaciones[$clave]['respuestas'][] = $m[1];
        }
    }

    return $operaciones;
}

/** @return list<string> valores del enum de Error.code */
function codigosDeErrorDelContrato(): array
{
    $texto = (string) file_get_contents(dirname(__DIR__, 2) . '/api/openapi.yaml');
    preg_match('/            code:\n              type: string\n              enum:\n((?:                - \S+\n)+)/', $texto, $m);
    preg_match_all('/- (\S+)/', $m[1] ?? '', $c);

    return $c[1];
}

/** @return array<string, string> "METODO /ruta" => permiso, desde la tabla de rutas real */
function operacionesDelCodigo(): array
{
    $ops = [];
    foreach ((new Escenario())->kernel->rutas() as $r) {
        $ops[$r->metodo . ' ' . $r->plantilla] = $r->permiso;
    }

    return $ops;
}

/** Ejecuta escenarios reales y devuelve [operación del contrato, estado, código de error]. */
function escenariosReales(): array
{
    $e = new Escenario();
    $k = $e->kernel;
    $vistos = [];
    $anotar = static function (string $op, NotasSoap\Presentacion\Http\Respuesta $r) use (&$vistos): void {
        $vistos[] = [$op, (string) $r->estado, $r->cuerpo['error']['code'] ?? null];
    };
    $POST = 'POST /api/v1/notas-soap';
    $GET = 'GET /api/v1/notas-soap/{notaId}';
    $FIRMA = 'POST /api/v1/notas-soap/{notaId}/firma';
    $LISTA = 'GET /api/v1/expedientes/{expedienteId}/notas-soap';

    $r = $k->manejar(Escenario::peticion([], ['idempotency-key' => 'clave-contrato-01']));
    $anotar($POST, $r);
    $id = $r->cuerpo['clinical_note_id'];
    $anotar($POST, $k->manejar(Escenario::peticion([], ['idempotency-key' => 'clave-contrato-01'])));
    $anotar($POST, $k->manejar(Escenario::peticion(['plan' => 'otro'], ['idempotency-key' => 'clave-contrato-01'])));
    $anotar($POST, $k->manejar(Escenario::peticion([], ['idempotency-key' => 'corta'])));
    $anotar($POST, $k->manejar(Escenario::peticion([], ['x-tenant-id' => ''])));
    $anotar($POST, $k->manejar(Escenario::peticion(['diagnostico' => 'J18.9'])));
    $anotar($POST, $k->manejar(Escenario::peticion([], ['authorization' => 'Bearer x'])));
    $anotar($POST, $k->manejar(Escenario::peticion([], ['authorization' => 'Bearer token-ana'])));
    $anotar($POST, $k->manejar(Escenario::peticion([], ['authorization' => 'Bearer token-lopez'])));
    $anotar($POST, $k->manejar(Escenario::peticion(['medical_record_id' => 'exp-rosa'])));
    $anotar($POST, $k->manejar(Escenario::peticion(['plan' => ''])));
    $anotar($POST, $k->manejar(Escenario::peticion(['diagnostico' => ['code' => 'X', 'description' => 'x', 'type' => 'primary']])));
    $anotar($GET, $k->manejar(Escenario::peticion([], [], 'GET', "/api/v1/notas-soap/$id")));
    $anotar($GET, $k->manejar(Escenario::peticion([], [], 'GET', '/api/v1/notas-soap/no-existe')));
    $anotar($GET, $k->manejar(Escenario::peticion([], ['x-tenant-id' => 'hosp-central'], 'GET', "/api/v1/notas-soap/$id")));
    $anotar($FIRMA, $k->manejar(Escenario::peticion([], ['authorization' => 'Bearer token-lopez'], 'POST', "/api/v1/notas-soap/$id/firma")));
    $anotar($FIRMA, $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma")));
    $anotar($FIRMA, $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma")));
    $anotar($FIRMA, $k->manejar(Escenario::peticion([], [], 'POST', '/api/v1/notas-soap/no-existe/firma')));
    $anotar($FIRMA, $k->manejar(Escenario::peticion([], ['authorization' => 'Bearer token-ana'], 'POST', "/api/v1/notas-soap/$id/firma")));
    $anotar($LISTA, $k->manejar(Escenario::peticion([], ['authorization' => 'Bearer token-ana'], 'GET', '/api/v1/expedientes/exp-carlos/notas-soap')));
    $anotar($LISTA, $k->manejar(Escenario::peticion([], [], 'GET', '/api/v1/expedientes/exp-carlos/notas-soap', ['estado' => 'archivada'])));
    $anotar($LISTA, $k->manejar(Escenario::peticion([], ['x-tenant-id' => 'hosp-central'], 'GET', '/api/v1/expedientes/exp-carlos/notas-soap')));
    $anotar($POST, (new Escenario(diagnosticos: new NotasSoap\Pruebas\Dobles\RepositorioDiagnosticosQueFalla()))->kernel->manejar(Escenario::peticion()));

    return $vistos;
}

prueba('API-01', 'Contrato ↔ código: mismas operaciones (método y ruta) y mismo permiso (x-permiso) en cada una', function (): void {
    $contrato = array_map(static fn (array $o): ?string => $o['permiso'], operacionesDelContrato());
    $codigo = operacionesDelCodigo();
    ksort($contrato);
    ksort($codigo);
    igual(array_keys($codigo), array_keys($contrato), 'operaciones');
    igual($codigo, $contrato, 'permisos por operación');
});

prueba('API-02', 'Toda operación documenta 400, 401, 403 y 500, y al menos una respuesta 2xx', function (): void {
    $faltantes = [];
    foreach (operacionesDelContrato() as $clave => $o) {
        foreach (array_diff(['400', '401', '403', '500'], $o['respuestas']) as $codigo) {
            $faltantes[] = "$clave sin $codigo";
        }
        if (array_filter($o['respuestas'], static fn (string $c): bool => $c[0] === '2') === []) {
            $faltantes[] = "$clave sin 2xx";
        }
    }
    afirmar($faltantes === [], implode('; ', $faltantes));
});

prueba('API-03', 'Cada código HTTP que devuelve el servidor en 24 escenarios reales está documentado en su operación', function (): void {
    $contrato = operacionesDelContrato();
    $faltan = [];
    foreach (escenariosReales() as [$op, $estado]) {
        if (!in_array($estado, $contrato[$op]['respuestas'] ?? [], true)) {
            $faltan[] = "$op → $estado";
        }
    }
    afirmar($faltan === [], 'no documentados: ' . implode('; ', array_unique($faltan)));
});

prueba('API-04', 'Cada código de error que devuelve el servidor está en el enum Error.code del contrato', function (): void {
    $enum = codigosDeErrorDelContrato();
    afirmar(count($enum) >= 10, 'no se pudo leer el enum del contrato');
    $devueltos = array_values(array_unique(array_filter(array_column(escenariosReales(), 2))));
    $faltan = array_diff($devueltos, $enum);
    afirmar($faltan === [], 'códigos no documentados: ' . implode(', ', $faltan));
    afirmar(count($devueltos) >= 9, 'los escenarios deben cubrir al menos 9 códigos de error distintos; cubren ' . count($devueltos));
});
