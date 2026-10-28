<?php
declare(strict_types=1);

// Evidencia de la semana 5. Uso: php verificar-contrato.php [ruta/openapi.yaml]   (sale con 1 si algo falla)
$yaml = (string) file_get_contents($argv[1] ?? __DIR__ . '/api/openapi.yaml');
$total = $fallas = 0;
function ver(string $id, bool $ok, string $detalle): void {
    $GLOBALS['total']++; $GLOBALS['fallas'] += $ok ? 0 : 1;
    printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $detalle);
}

// Parte el contrato en operaciones: "  /ruta:" y debajo "    metodo:" hasta volver a la sangría 4 o menos.
$ops = []; $ruta = ''; $actual = null;
foreach (explode("\n", $yaml) as $l) {
    if (preg_match('#^  (/\S+):\s*$#', $l, $m)) { $ruta = $m[1]; $actual = null; continue; }
    if ($ruta !== '' && preg_match('/^    (get|post|patch|put|delete):\s*$/', $l, $m)) { $actual = strtoupper($m[1]) . ' ' . $ruta; $ops[$actual] = ''; continue; }
    if ($actual !== null) { if (preg_match('/^ {0,4}\S/', $l)) { $actual = null; } else { $ops[$actual] .= $l . "\n"; } }
}
$esperadas = ['POST /api/v1/medicos', 'GET /api/v1/medicos', 'PATCH /api/v1/medicos/{medicoId}', 'POST /api/v1/medicos/{medicoId}/especialidades',
    'POST /api/v1/medicos/{medicoId}/horario', 'POST /api/v1/medicos/{medicoId}/ausencias', 'GET /api/v1/disponibilidad'];
$faltan = array_diff($esperadas, array_keys($ops));
ver('CON-01', !$faltan && count($ops) === 7, count($ops) . ' operaciones, las del servicio de la semana 2 (CU-02 y CU-07 van dentro de otras)' . ($faltan ? ' · faltan: ' . implode(', ', $faltan) : ''));

preg_match('/codigo:\s*type: string\s*enum: \[(.*?)\]/s', $yaml, $m);
$enum = array_map('trim', explode(',', $m[1] ?? ''));
$codigos = ['medico_duplicado', 'especialidad_invalida', 'especialidad_duplicada', 'rango_invalido', 'dato_invalido', 'citas_no_disponible', 'sin_sesion', 'acceso_denegado'];
ver('CON-02', !array_diff($codigos, $enum), count($enum) . ' códigos de error estables' . (($f = array_diff($codigos, $enum)) ? ' · faltan: ' . implode(', ', $f) : ' (RF-07, RN-08 y los de seguridad están)'));

$rotas = [];
preg_match_all('~#/components/(\w+)/(\w+)~', $yaml, $refs, PREG_SET_ORDER);
foreach ($refs as [$r, $tipo, $nombre]) { if (!preg_match("/^  $tipo:\s*$.*?^    $nombre:/ms", $yaml)) { $rotas[$r] = 1; } }
ver('CON-03', !$rotas, count($refs) . ' referencias $ref' . ($rotas ? ' · rotas: ' . implode(', ', array_keys($rotas)) : ' apuntan a algo que existe'));

$sinClave = array_filter(array_keys($ops), fn ($o) => str_starts_with($o, 'POST') && !str_contains($ops[$o], 'IdempotencyKey'));
ver('CON-04', !$sinClave, 'Todo POST acepta Idempotency-Key' . ($sinClave ? ' · sin ella: ' . implode(', ', $sinClave) : ' (un reintento no da 409 confuso)'));

$incompletas = array_filter(array_keys($ops), fn ($o) => !preg_match('/"401"/', $ops[$o]) || !preg_match('/"403"/', $ops[$o]) || !preg_match('/"500"/', $ops[$o]) || !str_contains($ops[$o], 'Rol:'));
ver('CON-05', !$incompletas, 'Cada operación declara su rol y responde 401, 403 y 500 (RNF-01)' . ($incompletas ? ' · incompletas: ' . implode(', ', $incompletas) : ''));

$disp = $ops['GET /api/v1/disponibilidad'] ?? '';
ver('CON-06', str_contains($disp, '"503"') && str_contains($disp, 'Retry-After') && str_contains($disp, '"422"'), 'Disponibilidad documenta 503 con Retry-After (RN-08) y 422 por rango (RN-07)');

// RNF-03: si está la semana 2, mide el servicio real con 100 médicos y 14 días. Si no está, se omite (no cuenta como falla).
$s2 = __DIR__ . '/../semana-02/ejemplo-isp/despues.php';
if (is_file($s2)) {
    require $s2;
    $citas = [];
    for ($i = 1; $i <= 1500; $i++) { $citas[] = ['medico' => 'med-' . (($i % 100) + 1), 'inicio' => sprintf('2026-10-%02d %02d:00', 28 + ($i % 3), 8 + ($i % 4))]; }
    $s = new Medicos\Despues\ServicioMedicos(['Cardiologia' => true], fn () => $citas, '2026-10-27');
    for ($i = 1; $i <= 100; $i++) {
        $id = $s->registrarMedico("C$i", "D$i", "Medico $i");
        $s->asociarEspecialidad($id, 'Cardiologia', '2026-01-01', null, true);
        for ($d = 1; $d <= 5; $d++) { $s->definirHorario($id, $d, '08:00', '12:00'); $s->definirHorario($id, $d, '14:00', '18:00'); }
    }
    $t = [];
    for ($r = 0; $r < 100; $r++) { $a = hrtime(true); $n = count($s->consultarDisponibilidad('Cardiologia', '2026-10-28', '2026-11-11')); $t[] = (hrtime(true) - $a) / 1e6; }
    sort($t);
    ver('RNF-03', $t[94] < 2000, sprintf('p95 de %.1f ms con 100 médicos y %d franjas en 14 días (meta < 2000 ms, dentro del proceso)', $t[94], $n));
} else {
    echo "RNF-03  OMITE falta la semana 2 (ejemplo-isp/despues.php); la medición se corre cuando esté fusionada\n";
}
printf("Total: %d  Pasan: %d  Fallan: %d\n", $total, $total - $fallas, $fallas);
exit($fallas ? 1 : 0);
