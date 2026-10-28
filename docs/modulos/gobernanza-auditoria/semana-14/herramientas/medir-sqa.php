<?php

declare(strict_types=1);

/**
 * Semana 14 · Mide las 7 métricas del plan SQA de Auditoría sobre el Micro-HIS REAL y las compara con su umbral.
 *
 * Uso: php herramientas/medir-sqa.php [--sin-mutaciones]   (desde docs/modulos/gobernanza-auditoria/semana-14)
 *   - Con AUDITORIA_DB_DSN definida, M1 incluye las pruebas de PostgreSQL y M2 las mutaciones que solo detecta la base.
 *   - SQA_MICROHIS mide otra copia (p. ej. con un defecto inyectado, para probar que las métricas lo detectan).
 * Salida: VERDE / ÁMBAR / ROJO por métrica. Código 0 si ninguna está en ROJO; 1 si alguna lo está.
 */
$micro = getenv('SQA_MICROHIS') ?: dirname(__DIR__, 2) . '/micro-his-auditoria';
$modulo = dirname(__DIR__, 2);
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Persistencia\FabricaAdaptadores;
use Auditoria\Presentacion\Http\Solicitud;

$metricas = [];
$anotar = static function (string $id, string $nombre, string $valor, string $umbral, string $estado, string $detalle) use (&$metricas): void {
    $metricas[] = compact('id', 'nombre', 'valor', 'umbral', 'estado', 'detalle');
};
$config = require "$micro/config/config.php";
$hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Guatemala')))->format('Y-m-d');
$nuevo = static function () use ($config): object {
    $a = FabricaAdaptadores::enMemoria();
    return (object) ['kernel' => crearKernel($config, $a), 'bitacora' => $a->bitacora];
};
$pedir = static fn (object $s, string $metodo, string $ruta, string $token, string $tenant, ?array $cuerpo = null, array $consulta = []) =>
    $s->kernel->manejar(new Solicitud($metodo, "/api/v1/auditoria$ruta", ['Authorization' => "Bearer $token", 'X-Tenant-ID' => $tenant], $consulta, $cuerpo));
$mov = static fn (string $evento, string $hospital, int $minuto) => ['event_id' => $evento, 'hospital' => $hospital, 'usuario' => 'u-' . ($minuto % 9),
    'modulo' => 'pacientes', 'accion' => 'PACIENTE_CONSULTADO', 'ocurrido_en' => sprintf('%sT00:%02d:%02d-06:00', $hoy, intdiv($minuto, 60) % 60, $minuto % 60),
    'resultado' => 'exito', 'recurso' => 'paciente:P-' . sprintf('%06d', $minuto)];

// ---------------------------------------------------------------------------------------------
// M1 · Calidad: pruebas automáticas en verde.
$salida = (string) shell_exec('php ' . escapeshellarg("$micro/tests/ejecutar.php") . ' 2>&1');
preg_match('/Total:\s*(\d+)\s+Pasan:\s*(\d+)\s+Fallan:\s*(\d+)\s+Omitidas:\s*(\d+)/', $salida, $m);
[$total, $pasan, $fallan, $omitidas] = array_map('intval', array_slice($m, 1, 4));
$anotar('M1', 'Pruebas automáticas en verde (calidad)', "$pasan/" . ($total - $omitidas) . " · $omitidas omitidas", '0 fallan y 0 omitidas en CI (PostgreSQL)',
    $fallan > 0 ? 'ROJO' : ($omitidas > 0 ? 'ÁMBAR' : 'VERDE'), $omitidas > 0 ? "las $omitidas omitidas requieren PostgreSQL (AUDITORIA_DB_DSN)" : 'incluye integración con PostgreSQL');

// ---------------------------------------------------------------------------------------------
// M2 · Calidad de las pruebas: mutaciones detectadas.
if (in_array('--sin-mutaciones', $argv, true)) {
    $anotar('M2', 'Defectos inyectados detectados (mutation score)', 'no medido', '100 %', 'ÁMBAR', 'se pidió --sin-mutaciones');
} else {
    $mut = (string) shell_exec('php ' . escapeshellarg("$micro/tests/mutaciones.php") . ' 2>&1');
    preg_match('/Mutaciones:\s*(\d+)\s+Detectadas:\s*(\d+)\s+Sobreviven:\s*(\d+)/', $mut, $mm);
    [$nMut, $detectadas] = [(int) ($mm[1] ?? 0), (int) ($mm[2] ?? 0)];
    $score = $nMut === 0 ? 0 : round(100 * $detectadas / $nMut, 1);
    $anotar('M2', 'Defectos inyectados detectados (mutation score)', "$score % ($detectadas/$nMut)", '100 %', $score === 100.0 ? 'VERDE' : 'ROJO',
        getenv('AUDITORIA_DB_DSN') ? 'con PostgreSQL' : 'sin PostgreSQL: M1 y M11 solo las detecta la base');
}

// ---------------------------------------------------------------------------------------------
// M3 · Confiabilidad: cadenas íntegras y tiempo de verificación (2 hospitales × 600 movimientos, más sus lecturas).
$s = $nuevo();
foreach (['hosp-oriente' => 'token-svc-pacientes-oriente', 'hosp-norte' => 'token-svc-pacientes-norte'] as $hospital => $token) {
    for ($i = 1; $i <= 600; $i++) {
        $pedir($s, 'POST', '/movimientos', $token, $hospital, $mov("$hospital-$i", $hospital, $i));
    }
}
$pedir($s, 'GET', '/movimientos', 'token-auditor-oriente', 'hosp-oriente', null, ['desde' => $hoy, 'hasta' => $hoy]);
$integras = 0;
$cadenas = ['hosp-oriente', 'hosp-norte', null];
$inicio = hrtime(true);
$verificados = 0;
foreach ($cadenas as $c) {
    $r = $pedir($s, 'POST', '/verificaciones', 'token-superadmin', '*', ['hospital' => $c]);
    $integras += ($r->cuerpo['estado'] ?? '') === 'INTEGRA' ? 1 : 0;
    $verificados += $r->cuerpo['verificados'] ?? 0;
}
$msPorMil = $verificados === 0 ? 0 : round((hrtime(true) - $inicio) / 1e6 / $verificados * 1000, 1);
$anotar('M3', 'Cadenas íntegras en la verificación (confiabilidad)', "$integras/" . count($cadenas) . " · $msPorMil ms por 1 000 movimientos", '100 % íntegras; ALTERADA o TRUNCADA = incidente',
    $integras === count($cadenas) ? 'VERDE' : 'ROJO', "$verificados movimientos verificados en 3 cadenas (2 hospitales + red)");

// ---------------------------------------------------------------------------------------------
// M4 · Seguridad: intentos de fuga entre tenants y sin permiso, bloqueados.
$s = $nuevo();
$n = $pedir($s, 'POST', '/movimientos', 'token-svc-pacientes-norte', 'hosp-norte', $mov('fuga-1', 'hosp-norte', 1))->cuerpo['id'] ?? 0;
$sondas = [
    ['auditor de Oriente lee un movimiento de Norte', fn () => $pedir($s, 'GET', "/movimientos/$n", 'token-auditor-oriente', 'hosp-oriente'), [404]],
    ['auditor de Oriente pide X-Tenant-ID de Norte', fn () => $pedir($s, 'GET', '/movimientos', 'token-auditor-oriente', 'hosp-norte', null, ['desde' => $hoy, 'hasta' => $hoy]), [403]],
    ['auditor de Oriente pide la red', fn () => $pedir($s, 'GET', '/movimientos', 'token-auditor-oriente', '*', null, ['desde' => $hoy, 'hasta' => $hoy]), [403]],
    ['auditor verifica integridad', fn () => $pedir($s, 'POST', '/verificaciones', 'token-auditor-oriente', 'hosp-oriente', ['hospital' => 'hosp-oriente']), [403]],
    ['auditor pide el ancla', fn () => $pedir($s, 'GET', '/anclas', 'token-auditor-oriente', 'hosp-oriente'), [403]],
    ['emisor de Oriente registra en Norte', fn () => $pedir($s, 'POST', '/movimientos', 'token-svc-pacientes-oriente', 'hosp-norte', $mov('fuga-2', 'hosp-norte', 2)), [403]],
    ['emisor de Oriente declara hospital Norte en el cuerpo', fn () => $pedir($s, 'POST', '/movimientos', 'token-svc-pacientes-oriente', 'hosp-oriente', $mov('fuga-3', 'hosp-norte', 3)), [403, 422]],
    ['emisor de Pacientes registra un movimiento de RBAC', fn () => $pedir($s, 'POST', '/movimientos', 'token-svc-pacientes-oriente', 'hosp-oriente', ['modulo' => 'rbac', 'accion' => 'ROL_ASIGNADO'] + $mov('fuga-4', 'hosp-oriente', 4)), [403, 422]],
    ['emisor consulta la bitácora', fn () => $pedir($s, 'GET', '/movimientos', 'token-svc-pacientes-oriente', 'hosp-oriente', null, ['desde' => $hoy, 'hasta' => $hoy]), [403]],
    ['borrar un movimiento', fn () => $pedir($s, 'DELETE', "/movimientos/$n", 'token-superadmin', '*'), [405]],
    ['modificar un movimiento', fn () => $pedir($s, 'PUT', "/movimientos/$n", 'token-superadmin', '*', ['usuario' => 'otro']), [405]],
    ['sin token', fn () => $pedir($s, 'GET', '/movimientos', '', 'hosp-oriente', null, ['desde' => $hoy, 'hasta' => $hoy]), [401]],
];
$fugas = [];
foreach ($sondas as [$nombre, $sonda, $esperados]) {
    $estado = $sonda()->estado;
    if (!in_array($estado, $esperados, true)) {
        $fugas[] = "$nombre → $estado";
    }
}
$bloqueadas = count($sondas) - count($fugas);
$anotar('M4', 'Intentos de fuga entre tenants / sin permiso bloqueados (seguridad)', "$bloqueadas/" . count($sondas), '100 %',
    $fugas === [] ? 'VERDE' : 'ROJO', $fugas === [] ? count($sondas) . ' sondas: tenant, red, permisos, emisor ajeno, DELETE/PUT y sin token' : 'NO bloqueadas: ' . implode(' · ', $fugas));

// ---------------------------------------------------------------------------------------------
// M5 · Trazabilidad: contrato = código y cada código de error de la API con su prueba.
$guardas = ['API-01', 'API-02', 'API-03', 'REC-04'];
$rojas = array_values(array_filter($guardas, static fn (string $g): bool => !preg_match('/\[PASA \]\s+' . $g . '\b/', $salida)));
$yaml = (string) file_get_contents("$micro/api/openapi.yaml");
// el enum de la propiedad «error» del esquema Error (ocupa varias líneas)
preg_match('/
    Error:.*?error:.*?enum:\s*\[([A-Z_,\s]+)\]/s', $yaml, $e);
$codigos = array_filter(array_map('trim', explode(',', $e[1] ?? '')));
$pruebas = implode("\n", array_map('file_get_contents', glob("$micro/tests/*/*.php")));
$sinPrueba = array_values(array_filter($codigos, static fn (string $c): bool => !str_contains($pruebas, "'$c'")));
$anotar('M5', 'Contrato = código y códigos de error con prueba (trazabilidad)', (count($guardas) - count($rojas)) . '/' . count($guardas) . ' guardas · ' . (count($codigos) - count($sinPrueba)) . '/' . count($codigos) . ' códigos',
    '100 % y 100 %', $rojas === [] && $sinPrueba === [] ? 'VERDE' : ($rojas !== [] ? 'ROJO' : 'ÁMBAR'),
    ($rojas ? 'guardas en rojo: ' . implode(', ', $rojas) . ' · ' : '') . ($sinPrueba ? 'códigos del contrato sin prueba que los afirme: ' . implode(', ', $sinPrueba) : 'todos los códigos del enum de errores aparecen en una prueba'));

// ---------------------------------------------------------------------------------------------
// M6 · Riesgo: hallazgos de la revisión técnica (semana 13) abiertos, por severidad.
$csv = array_map(static fn (string $l): array => str_getcsv($l, ',', '"', ''), file("$modulo/semana-13/plantillas/registro-hallazgos.csv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
$cab = array_shift($csv);
$abiertos = ['Crítico' => 0, 'Mayor' => 0, 'Menor' => 0, 'Observación' => 0];
foreach ($csv as $fila) {
    $h = array_combine($cab, $fila);
    if (!in_array($h['estado'], ['Cerrado', 'Rechazado', 'Aceptado (riesgo)'], true)) {
        $abiertos[$h['severidad']]++;
    }
}
$anotar('M6', 'Hallazgos de la revisión abiertos (riesgo)', "{$abiertos['Crítico']} críticos · {$abiertos['Mayor']} mayores · {$abiertos['Menor']} menores",
    '0 críticos; mayores con plazo ≤ 7 días', $abiertos['Crítico'] > 0 ? 'ROJO' : ($abiertos['Mayor'] > 0 ? 'ÁMBAR' : 'VERDE'), 'fuente: semana-13/plantillas/registro-hallazgos.csv');

// ---------------------------------------------------------------------------------------------
// M7 · Confiabilidad a futuro: capacidad de la verificación con el volumen proyectado de un año.
$antes = memory_get_usage();
$lista = [];
for ($i = 1; $i <= 10000; $i++) {
    $lista[] = Movimiento::reconstituir($i, 'svc-pacientes', "ev-$i", 'hosp-oriente', 'u-ana', 'pacientes', 'PACIENTE_CONSULTADO',
        new DateTimeImmutable('2026-10-21T15:00:00Z'), 'exito', 'paciente:P-000001', null, [], $i, str_repeat('a', 64));
}
$porMov = (memory_get_usage() - $antes) / 10000;
unset($lista);
$proyectado = 2000 * 365;      // movimientos por hospital y año (supuesto del plan §3, a calibrar con datos reales)
$necesarioMb = $proyectado * $porMov / 1048576;
$limiteMb = 128;
$anotar('M7', 'Capacidad de la verificación para 1 año de un hospital (confiabilidad)', sprintf('%.0f MB necesarios', $necesarioMb), "≤ 50 % del memory_limit ($limiteMb MB)",
    $necesarioMb <= $limiteMb / 2 ? 'VERDE' : ($necesarioMb <= $limiteMb ? 'ÁMBAR' : 'ROJO'), sprintf('%.0f B por movimiento × %s movimientos/año (2 000/día); la verificación carga toda la cadena (AR-02)', $porMov, number_format($proyectado, 0, ',', '.')));

// ---------------------------------------------------------------------------------------------
echo 'Plan SQA Auditoría · línea base ' . date('Y-m-d H:i') . ' · micro-his: ' . basename($micro) . PHP_EOL . str_repeat('-', 110) . PHP_EOL;
$rojos = 0;
foreach ($metricas as $x) {
    $rojos += $x['estado'] === 'ROJO' ? 1 : 0;
    printf("%-6s %-3s %s\n       valor: %s · umbral: %s\n       %s\n", $x['estado'], $x['id'], $x['nombre'], $x['valor'], $x['umbral'], $x['detalle']);
}
echo str_repeat('-', 110) . PHP_EOL . "En ROJO: $rojos" . PHP_EOL;
exit($rojos > 0 ? 1 : 0);
