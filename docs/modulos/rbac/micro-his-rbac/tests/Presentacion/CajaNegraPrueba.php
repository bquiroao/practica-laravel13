<?php

declare(strict_types=1);

/*
 * Semana 15 · Pruebas de CAJA NEGRA: diseñadas desde el contrato (api/openapi.yaml) y las reglas R-01..R-08,
 * sin mirar el código. Solo usan la interfaz pública HTTP (Kernel::manejar). Técnicas: particiones de
 * equivalencia, valores límite, matriz de permisos y conjetura de errores. Las operaciones clínicas
 * (prescripciones, signos vitales) son ajenas al módulo: de ellas solo se prueba la DECISIÓN de autorización.
 */

use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Pruebas\Dobles\Escenario;

const CN_TOKENS = [
    'token-admin-oriente' => ['admin-01', Escenario::H_ORIENTE],
    'token-admin-norte' => ['admin-02', Escenario::H_NORTE],
    'token-marta' => ['marta', Escenario::H_ORIENTE],     // médico vigente
    'token-ana' => ['ana', Escenario::H_ORIENTE],         // enfermería vigente
    'token-pedro' => ['pedro', Escenario::H_ORIENTE],     // médico vencido el 2026-06-30
    'token-lucia' => ['lucia', Escenario::H_ORIENTE],     // enfermería revocada
    'token-nuevo' => ['nuevo-01', Escenario::H_ORIENTE],  // sin roles
];

/** Sistema completo y nuevo en cada llamada, con más identidades que el Escenario base. */
function cajaNegra(): array
{
    $e = new Escenario();
    $kernel = construirKernel($e->casos(), new AutenticadorTokenDemo(CN_TOKENS));
    $pedir = static fn (string $metodo, string $ruta, ?string $token, ?array $cuerpo = null, ?string $tenant = Escenario::H_ORIENTE)
        => $kernel->manejar(Escenario::http($metodo, $ruta, $token, $tenant, $cuerpo));
    return [$e, $pedir];
}

const CN_ASIGNAR = '/api/v1/roles/asignaciones';
const CN_REVOCAR = '/api/v1/roles/revocaciones';

prueba('CN-01', 'Equivalencia · asignar rol: 1 clase válida y 6 inválidas, cada una con su código del contrato', function (): void {
    $clases = [
        'válida' => [['usuario_id' => 'nuevo-01', 'rol' => 'medico', 'valido_desde' => '2026-11-15'], 201, 'ROL_ASIGNADO'],
        'usuario inexistente' => [['usuario_id' => 'nadie', 'rol' => 'medico', 'valido_desde' => '2026-11-15'], 404, 'USUARIO_NO_ENCONTRADO'],
        'usuario inactivo' => [['usuario_id' => 'jorge', 'rol' => 'medico', 'valido_desde' => '2026-11-15'], 422, 'USUARIO_INACTIVO'],
        'rol fuera del catálogo' => [['usuario_id' => 'nuevo-01', 'rol' => 'director', 'valido_desde' => '2026-11-15'], 422, 'ROL_INVALIDO'],
        'autoasignación' => [['usuario_id' => 'admin-01', 'rol' => 'medico', 'valido_desde' => '2026-11-15'], 422, 'AUTOASIGNACION'],
        'duplicado abierto' => [['usuario_id' => 'ana', 'rol' => 'enfermeria', 'valido_desde' => '2026-11-15'], 409, 'ASIGNACION_DUPLICADA'],
        'formato inválido' => [['usuario_id' => 'nuevo-01', 'rol' => 'medico', 'valido_desde' => '15/11/2026'], 422, 'DATOS_INVALIDOS'],
    ];
    foreach ($clases as $clase => [$cuerpo, $estado, $codigo]) {
        [, $pedir] = cajaNegra();
        $r = $pedir('POST', CN_ASIGNAR, 'token-admin-oriente', $cuerpo);
        igual([$estado, $codigo], [$r->estado, $r->cuerpo['codigo'] ?? null], $clase);
    }
});

prueba('CN-02', 'Límites · motivo de revocación: 9 → 422 MOTIVO_INSUFICIENTE · 10 → 200 · 500 → 200 · 501 → 422 DATOS_INVALIDOS', function (): void {
    foreach ([9 => [422, 'MOTIVO_INSUFICIENTE'], 10 => [200, 'ROL_REVOCADO'], 500 => [200, 'ROL_REVOCADO'], 501 => [422, 'DATOS_INVALIDOS']] as $largo => [$estado, $codigo]) {
        [, $pedir] = cajaNegra();
        $r = $pedir('POST', CN_REVOCAR, 'token-admin-oriente', ['usuario_id' => 'ana', 'rol' => 'enfermeria', 'motivo' => str_repeat('m', $largo)]);
        igual([$estado, $codigo], [$r->estado, $r->cuerpo['codigo'] ?? null], "motivo de $largo caracteres");
    }
});

prueba('CN-03', 'Límites · vigencia, referencia de auditoría (1–64) y lista de permisos efectivos (1–20)', function (): void {
    [, $pedir] = cajaNegra();
    $vig = static fn (string $hasta) => $pedir('POST', CN_ASIGNAR, 'token-admin-oriente', ['usuario_id' => 'nuevo-01', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15', 'valido_hasta' => $hasta]);
    igual([422, 'VIGENCIA_INVALIDA'], [($r = $vig('2026-11-15'))->estado, $r->cuerpo['codigo'] ?? null], 'hasta = desde');
    igual(201, $vig('2026-11-16')->estado, 'hasta = desde + 1 día');

    // El contrato fija el patrón {1,64} y que la ruta exige auditoria.consultar: para alguien SIN ese permiso,
    // una referencia dentro del límite llega a la ruta protegida (403) y una fuera del límite no existe (404).
    $ref = static fn (int $n) => $pedir('GET', '/api/v1/auditoria/eventos/' . str_repeat('a', $n), 'token-ana')->estado;
    igual([403, 403, 404], [$ref(1), $ref(64), $ref(65)], 'referencias de 1, 64 y 65 caracteres (sin permiso de auditoría)');
    igual(404, $pedir('GET', '/api/v1/auditoria/eventos/' . str_repeat('a', 64), 'token-admin-oriente')->estado, 'referencia de 64 con permiso: válida pero inexistente');

    $lista = static fn (int $n) => $pedir('POST', '/api/v1/autorizaciones/permisos-efectivos', 'token-ana', ['permisos' => array_map(static fn (int $i): string => "modulo." . str_repeat("x", $i), $n ? range(1, $n) : [])]);
    igual([422, 200, 200, 422], [$lista(0)->estado, $lista(1)->estado, $lista(20)->estado, $lista(21)->estado], 'listas de 0, 1, 20 y 21 permisos');
});

prueba('CN-04', 'Permisos · matriz 7 identidades × 5 rutas protegidas: solo pasa quien tiene el permiso; el 403 es genérico y queda auditado con su motivo', function (): void {
    $rutas = [
        'asignar' => ['POST', CN_ASIGNAR, ['usuario_id' => 'nuevo-01', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15']],
        'revocar' => ['POST', CN_REVOCAR, ['usuario_id' => 'ana', 'rol' => 'enfermeria', 'motivo' => 'Prueba de la matriz de permisos']],
        'auditoría' => ['GET', '/api/v1/auditoria/eventos/sin-evento', null],
        'prescribir' => ['POST', '/api/v1/prescripciones', null],
        'signos' => ['POST', '/api/v1/signos-vitales', null],
    ];
    // Esperado según el catálogo (S1/S2): ✓ = pasa la autorización (cualquier estado ≠ 403), motivo = lo que debe quedar en la bitácora.
    $esperado = [
        'token-admin-oriente' => ['✓', '✓', '✓', 'PERMISO_NO_ASIGNADO', 'PERMISO_NO_ASIGNADO'],
        'token-marta' => ['PERMISO_NO_ASIGNADO', 'PERMISO_NO_ASIGNADO', 'PERMISO_NO_ASIGNADO', '✓', '✓'],
        'token-ana' => ['PERMISO_NO_ASIGNADO', 'PERMISO_NO_ASIGNADO', 'PERMISO_NO_ASIGNADO', 'PERMISO_NO_ASIGNADO', '✓'],
        'token-pedro' => array_fill(0, 5, 'MEMBRESIA_NO_VIGENTE'),
        'token-lucia' => array_fill(0, 5, 'MEMBRESIA_NO_VIGENTE'),
        'token-nuevo' => array_fill(0, 5, 'MEMBRESIA_NO_VIGENTE'),
        'token-admin-norte' => array_fill(0, 5, 'TENANT_NO_COINCIDE'),
    ];
    $difieren = [];
    foreach ($esperado as $token => $fila) {
        foreach (array_values($rutas) as $i => [$metodo, $ruta, $cuerpo]) {
            [$e, $pedir] = cajaNegra();
            $r = $pedir($metodo, $ruta, $token, $cuerpo);
            if ($fila[$i] === '✓') {
                $obtenido = $r->estado === 403 ? '403' : '✓';
            } else {
                $motivo = $e->auditoria->buscarPorCorrelacion((string) ($r->cuerpo['correlation_id'] ?? ''))?->datos['motivo'] ?? 'sin evento';
                $generico = array_keys($r->cuerpo) === ['mensaje', 'correlation_id'] && $r->cuerpo['mensaje'] === 'Acceso denegado.';
                $obtenido = $r->estado === 403 && $generico ? $motivo : "HTTP {$r->estado}";
            }
            if ($obtenido !== $fila[$i]) {
                $difieren[] = "$token × " . array_keys($rutas)[$i] . ": esperado {$fila[$i]}, obtenido $obtenido";
            }
        }
    }
    igual([], $difieren, '35 celdas de la matriz');
});

prueba('CN-05', 'Errores · 400 sin hospital, 401 sin token o con token inválido, 404 ruta, 405 método, 422 cuerpo ausente, 404 de auditoría para otro hospital', function (): void {
    [$e, $pedir] = cajaNegra();
    igual([400, 'La cabecera X-Tenant-ID es obligatoria.'], [($r = $pedir('POST', '/api/v1/signos-vitales', 'token-ana', null, ''))->estado, $r->cuerpo['mensaje'] ?? null], 'sin X-Tenant-ID');
    igual(401, $pedir('POST', '/api/v1/signos-vitales', null)->estado, 'sin Authorization');
    igual(401, $pedir('POST', '/api/v1/signos-vitales', 'token-inventado')->estado, 'token inválido');
    igual(404, $pedir('GET', '/api/v1/no-existe', 'token-ana')->estado, 'ruta inexistente');
    igual(405, $pedir('DELETE', CN_ASIGNAR, 'token-admin-oriente')->estado, 'método no permitido');
    igual([422, ['usuario_id', 'rol', 'valido_desde']], [($r = $pedir('POST', CN_ASIGNAR, 'token-admin-oriente', null))->estado, $r->cuerpo['campos'] ?? null], 'cuerpo ausente');
    $ref = (string) $pedir('POST', '/api/v1/prescripciones', 'token-ana')->cuerpo['correlation_id'];
    igual(200, $pedir('GET', "/api/v1/auditoria/eventos/$ref", 'token-admin-oriente')->estado, 'el auditor de Oriente lo ve');
    $norte = $pedir('GET', "/api/v1/auditoria/eventos/$ref", 'token-admin-norte', null, Escenario::H_NORTE);
    $inexistente = $pedir('GET', '/api/v1/auditoria/eventos/no-existe-nada', 'token-admin-norte', null, Escenario::H_NORTE);
    igual([$inexistente->estado, $inexistente->cuerpo], [$norte->estado, $norte->cuerpo], 'otro hospital: misma respuesta que «no existe» (no confirma existencia)');
});

prueba('CN-06', 'Denegación trazable de punta a punta: 403 → el auditor encuentra el evento con persona, permiso y motivo; la persona denegada no puede verlo', function (): void {
    [, $pedir] = cajaNegra();
    $pedir('POST', CN_REVOCAR, 'token-admin-oriente', ['usuario_id' => 'ana', 'rol' => 'enfermeria', 'motivo' => 'Traslado a consulta externa']);
    $denegado = $pedir('POST', '/api/v1/signos-vitales', 'token-ana');
    igual(403, $denegado->estado, 'Ana tras la revocación');
    $evento = $pedir('GET', '/api/v1/auditoria/eventos/' . $denegado->cuerpo['correlation_id'], 'token-admin-oriente');
    igual([200, 'ACCESO_DENEGADO', 'ana', 'signos_vitales.registrar', 'MEMBRESIA_NO_VIGENTE'],
        [$evento->estado, $evento->cuerpo['tipo'] ?? null, $evento->cuerpo['actor'] ?? null, $evento->cuerpo['datos']['permiso'] ?? null, $evento->cuerpo['datos']['motivo'] ?? null], 'evento');
    igual(403, $pedir('GET', '/api/v1/auditoria/eventos/' . $denegado->cuerpo['correlation_id'], 'token-ana')->estado, 'Ana no puede consultar la auditoría');
});
