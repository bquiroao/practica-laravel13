<?php

declare(strict_types=1);

/*
 * Contrato API: el archivo api/openapi.yaml y el código deben decir lo mismo.
 * Si alguien agrega una ruta, cambia un permiso o devuelve un código no documentado,
 * estas pruebas fallan.
 */

use Rbac\Presentacion\Http\Controladores\AsignacionRolControlador;
use Rbac\Presentacion\Http\Controladores\AuditoriaControlador;
use Rbac\Presentacion\Http\Controladores\AutorizacionControlador;
use Rbac\Presentacion\Http\Controladores\OperacionClinicaControlador;
use Rbac\Presentacion\Http\Ruta;
use Rbac\Presentacion\Http\Rutas;
use Rbac\Pruebas\Dobles\Escenario;

/**
 * Lector mínimo de la sección «paths» del OpenAPI (formato controlado del archivo).
 *
 * @return array<string, array{permiso: ?string, respuestas: list<string>}> clave "METODO /ruta"
 */
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
            $operaciones[$clave]['permiso'] = $m[1] === 'null' ? null : $m[1];
        } elseif ($clave !== null && preg_match('/^      (\S+):/', $linea, $m) === 1) {
            $enRespuestas = $m[1] === 'responses';
        } elseif ($clave !== null && $enRespuestas && preg_match("/^        '(\d{3})':$/", $linea, $m) === 1) {
            $operaciones[$clave]['respuestas'][] = $m[1];
        }
    }

    return $operaciones;
}

/** @return array<string, ?string> clave "METODO /ruta" => permiso, desde la tabla de rutas real */
function operacionesDelCodigo(): array
{
    $e = new Escenario();
    $rutas = Rutas::definir(
        new AsignacionRolControlador($e->asignarRol),
        new AuditoriaControlador($e->consultar),
        new OperacionClinicaControlador(),
        new AutorizacionControlador($e->autorizar),
    );

    $operaciones = [];
    foreach ($rutas as $ruta) {
        // '#^/api/v1/x/(?<id>[A-Za-z0-9-]{1,64})$#' → '/api/v1/x/{id}'
        $plantilla = preg_replace('/\(\?<(\w+)>[^)]*\)/', '{$1}', substr($ruta->patron, 2, -2));
        $operaciones[$ruta->metodo . ' ' . $plantilla] = $ruta->permiso;
    }

    return $operaciones;
}

prueba('API-01', 'Contrato ↔ código: mismas operaciones (método y ruta) y mismo permiso (x-permiso) en cada una', function (): void {
    $contrato = array_map(static fn (array $o): ?string => $o['permiso'], operacionesDelContrato());
    $codigo = operacionesDelCodigo();
    ksort($contrato);
    ksort($codigo);
    igual(array_keys($codigo), array_keys($contrato), 'operaciones');
    igual($codigo, $contrato, 'permisos por operación');
});

prueba('API-02', 'Toda operación documenta 400, 401 y 500; las protegidas por permiso documentan además 403', function (): void {
    $faltantes = [];
    foreach (operacionesDelContrato() as $clave => $operacion) {
        $requeridas = $operacion['permiso'] === null ? ['400', '401', '500'] : ['400', '401', '403', '500'];
        foreach (array_diff($requeridas, $operacion['respuestas']) as $codigo) {
            $faltantes[] = "$clave sin $codigo";
        }
    }
    afirmar($faltantes === [], implode('; ', $faltantes));
});

prueba('API-03', 'Cada código HTTP que devuelve el servidor en escenarios reales está documentado en el contrato', function (): void {
    $e = new Escenario();
    $cid = (string) $e->kernel->manejar(Escenario::http('POST', '/api/v1/prescripciones', 'token-ana'))->cuerpo['correlation_id'];
    $asignar = static fn (array $cuerpo): array => ['POST', '/api/v1/roles/asignaciones', 'token-admin-oriente', Escenario::H_ORIENTE, $cuerpo];
    $escenarios = [
        $asignar(['usuario_id' => 'nuevo-01', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15']),
        $asignar(['usuario_id' => 'ana', 'rol' => 'enfermeria', 'valido_desde' => '2026-11-15']),
        $asignar(['usuario_id' => 'fantasma', 'rol' => 'medico', 'valido_desde' => '2026-11-15']),
        $asignar(['usuario_id' => 'nuevo-01', 'rol' => 'superusuario', 'valido_desde' => '2026-11-15']),
        $asignar(['rol' => 'medico']),
        ['POST', '/api/v1/roles/asignaciones', 'token-ana', Escenario::H_ORIENTE, ['usuario_id' => 'x', 'rol' => 'medico', 'valido_desde' => '2026-11-15']],
        ['POST', '/api/v1/roles/asignaciones', 'token-falso', Escenario::H_ORIENTE, null],
        ['POST', '/api/v1/roles/asignaciones', 'token-admin-oriente', null, null],
        ['POST', '/api/v1/autorizaciones/decisiones', 'token-ana', Escenario::H_ORIENTE, ['permiso' => 'signos_vitales.registrar', 'recurso' => 'r']],
        ['POST', '/api/v1/autorizaciones/decisiones', 'token-ana', Escenario::H_ORIENTE, ['permiso' => 'X', 'recurso' => '']],
        ['GET', '/api/v1/auditoria/eventos/' . $cid, 'token-admin-oriente', Escenario::H_ORIENTE, null],
        ['GET', '/api/v1/auditoria/eventos/no-existe', 'token-admin-oriente', Escenario::H_ORIENTE, null],
        ['GET', '/api/v1/auditoria/eventos/' . $cid, 'token-ana', Escenario::H_ORIENTE, null],
        ['POST', '/api/v1/prescripciones', 'token-marta', Escenario::H_ORIENTE, null],
        ['POST', '/api/v1/signos-vitales', 'token-ana', Escenario::H_ORIENTE, null],
    ];

    $contrato = operacionesDelContrato();
    $noDocumentados = [];
    $vistos = 0;
    foreach ($escenarios as [$metodo, $ruta, $token, $tenant, $cuerpo]) {
        $estado = $e->kernel->manejar(Escenario::http($metodo, $ruta, $token, $tenant, $cuerpo))->estado;
        $clave = $metodo . ' ' . preg_replace('#/api/v1/auditoria/eventos/.+$#', '/api/v1/auditoria/eventos/{correlationId}', $ruta);
        if (!in_array((string) $estado, $contrato[$clave]['respuestas'] ?? [], true)) {
            $noDocumentados[] = "$clave → $estado";
        }
        $vistos++;
    }
    igual(15, $vistos, 'escenarios ejecutados');
    afirmar($noDocumentados === [], 'códigos no documentados: ' . implode(', ', $noDocumentados));
});
