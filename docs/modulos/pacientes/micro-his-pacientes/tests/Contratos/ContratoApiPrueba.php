<?php

declare(strict_types=1);

/*
 * Conformidad entre el CONTRATO (api/openapi.yaml) y el CÓDIGO: si alguien cambia uno sin el
 * otro, estas pruebas fallan. Se lee el YAML con un lector mínimo (sin dependencias) que
 * conoce la indentación fija del archivo.
 */

use Pacientes\Aplicacion\Servicios\ControlAcceso;
use Pacientes\Presentacion\Http\Controladores\PacientesControlador;
use Pacientes\Presentacion\Http\Rutas;
use Pacientes\Pruebas\Dobles\Escenario;

/** @return array<string, array{operationId: string, permiso: ?string, respuestas: list<string>}> «MÉTODO /ruta» → datos */
function operacionesDelContrato(): array
{
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
            $ruta = $metodo = null; // salió de «paths:»
        }
    }

    return $operaciones;
}

/** @return list<string> «MÉTODO /ruta» con los parámetros como {nombre} */
function operacionesDelCodigo(): array
{
    $e = Escenario::crear();
    $c = new PacientesControlador($e->casos['registrar'], $e->casos['buscar'], $e->casos['consultar'], $e->casos['actualizar']);

    return array_map(static fn ($r): string => $r->metodo . ' ' . preg_replace(['/^#\^|\$#$/', '/\(\?<(\w+)>[^)]*\)/'], ['', '{$1}'], $r->patron),
        Rutas::definir($c));
}

prueba('API-01', 'Contrato = código: mismas operaciones; cada x-permiso existe y es el que exige su caso de uso', function (): void {
    $contrato = operacionesDelContrato();
    $codigo = operacionesDelCodigo();
    $a = array_keys($contrato);
    sort($a);
    sort($codigo);
    igual($a, $codigo, 'operaciones');
    $constantes = (new ReflectionClass(ControlAcceso::class))->getConstants();
    foreach ($contrato as $operacion => $datos) {
        $constante = array_search($datos['permiso'], $constantes, true);
        afirmar($constante !== false, "$operacion: x-permiso «{$datos['permiso']}» no existe en ControlAcceso");
        $casoDeUso = dirname(__DIR__, 2) . '/src/Aplicacion/CasosDeUso/' . ucfirst(str_replace('Pacientes', 'Pacientes', $datos['operationId'])) . '.php';
        afirmar(is_file($casoDeUso), "$operacion: no hay caso de uso para {$datos['operationId']}");
        afirmar(str_contains((string) file_get_contents($casoDeUso), "ControlAcceso::$constante)"), "$operacion: el caso de uso no exige $constante");
    }
});

prueba('API-02', 'Contrato completo: toda operación documenta 400/401/403/500/503; escrituras 422; PUT 409 y 428; detalle 304 y 404', function (): void {
    $faltan = [];
    foreach (operacionesDelContrato() as $operacion => $datos) {
        $requeridas = ['400', '401', '403', '500', '503'];
        if (str_starts_with($operacion, 'POST') || str_starts_with($operacion, 'PUT')) {
            $requeridas[] = '422';
        }
        if (str_starts_with($operacion, 'PUT')) {
            array_push($requeridas, '409', '428');
        }
        if ($operacion === 'GET /api/v1/pacientes/{codigo}') {
            array_push($requeridas, '304', '404');
        }
        foreach (array_diff($requeridas, $datos['respuestas']) as $codigo) {
            $faltan[] = "$operacion → $codigo";
        }
    }
    afirmar($faltan === [], 'sin documentar: ' . implode(', ', $faltan));
});

prueba('API-03', 'Cada código que la API devuelve en 18 escenarios reales está documentado en el contrato', function (): void {
    $contrato = operacionesDelContrato();
    $e = Escenario::crear();
    $k = $e->kernel;
    $ana = Escenario::json(Escenario::datosAna());
    $escenarios = [
        ['POST /api/v1/pacientes', Escenario::http('POST', '/api/v1/pacientes', cuerpo: $ana)],
        ['POST /api/v1/pacientes', Escenario::http('POST', '/api/v1/pacientes', cuerpo: $ana)],
        ['POST /api/v1/pacientes', Escenario::http('POST', '/api/v1/pacientes', cuerpo: null)],
        ['POST /api/v1/pacientes', Escenario::http('POST', '/api/v1/pacientes', cuerpo: Escenario::json(Escenario::datosAna(['sexo' => 'x'])))],
        ['POST /api/v1/pacientes', Escenario::http('POST', '/api/v1/pacientes', 'token-clinico-oriente', cuerpo: $ana)],
        ['POST /api/v1/pacientes', Escenario::http('POST', '/api/v1/pacientes', null, cuerpo: $ana)],
        ['GET /api/v1/pacientes', Escenario::http('GET', '/api/v1/pacientes', consulta: ['nombre' => 'lopez'])],
        ['GET /api/v1/pacientes', Escenario::http('GET', '/api/v1/pacientes', consulta: ['nombre' => 'lo'])],
        ['GET /api/v1/pacientes', Escenario::http('GET', '/api/v1/pacientes', 'token-admision-norte')],
        ['GET /api/v1/pacientes/{codigo}', Escenario::http('GET', '/api/v1/pacientes/P-000001')],
        ['GET /api/v1/pacientes/{codigo}', Escenario::http('GET', '/api/v1/pacientes/P-000001', cabeceras: ['If-None-Match' => '"1"'])],
        ['GET /api/v1/pacientes/{codigo}', Escenario::http('GET', '/api/v1/pacientes/P-999999')],
        ['GET /api/v1/pacientes/{codigo}', new Pacientes\Presentacion\Http\Solicitud('GET', '/api/v1/pacientes/P-000001')],
        ['PUT /api/v1/pacientes/{codigo}', Escenario::http('PUT', '/api/v1/pacientes/P-000001', cuerpo: $ana)],
        ['PUT /api/v1/pacientes/{codigo}', Escenario::http('PUT', '/api/v1/pacientes/P-000001', cuerpo: $ana, cabeceras: ['If-Match' => '"1"'])],
        ['PUT /api/v1/pacientes/{codigo}', Escenario::http('PUT', '/api/v1/pacientes/P-000001', cuerpo: $ana, cabeceras: ['If-Match' => '"1"'])],
        ['PUT /api/v1/pacientes/{codigo}', Escenario::http('PUT', '/api/v1/pacientes/P-000001', cuerpo: Escenario::json(Escenario::datosAna(['nombres' => 'Otra'])), cabeceras: ['If-Match' => '"2"'])],
        ['PUT /api/v1/pacientes/{codigo}', Escenario::http('PUT', '/api/v1/pacientes/P-000099', cuerpo: $ana, cabeceras: ['If-Match' => '"1"'])],
    ];
    $vistos = [];
    $sinDocumentar = [];
    foreach ($escenarios as [$operacion, $solicitud]) {
        $estado = (string) $k->manejar($solicitud)->estado;
        $vistos[] = "$operacion → $estado";
        if (!in_array($estado, $contrato[$operacion]['respuestas'], true)) {
            $sinDocumentar[] = "$operacion → $estado";
        }
    }
    afirmar($sinDocumentar === [], 'códigos sin documentar: ' . implode(', ', $sinDocumentar));
    afirmar(count(array_unique($vistos)) >= 14, 'los escenarios no cubren suficientes respuestas distintas: ' . implode(', ', array_unique($vistos)));
});
