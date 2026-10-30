<?php
declare(strict_types=1);

// Semana 10 — Comprueba que las tablas, columnas e índices que usa la propuesta móvil existen (o faltan, cuando así se dice). Solo PHP.
// Uso desde la raíz del repo: php docs/modulos/verificacion-bd-postgresql/semana-10/verificar-propuesta.php
$raiz = dirname(__DIR__, 4); $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$doc = (string) @file_get_contents(__DIR__ . '/semana-10-paginacion-sincronizacion-movil.md');
$sql = implode("\n", array_map('file_get_contents', array_merge(glob("$raiz/database/migrations/central/*.php") ?: [], glob("$raiz/database/migrations/hospital/*.php") ?: [])));
preg_match_all('/CREATE TABLE (\w+) \((.*?)(?=CREATE (?:TABLE|INDEX|UNIQUE|OR|TRIGGER|FUNCTION)|SQL\))/s', $sql, $t, PREG_SET_ORDER);
$tablas = []; foreach ($t as $x) { $tablas[$x[1]] = $x[2]; }
$tieneCol = fn (string $tabla, string $col) => (bool) preg_match('/(^|[\s(,])' . $col . ' (uuid|text|timestamptz|integer|numeric|date|bigint|jsonb)/', $tablas[$tabla] ?? '');

preg_match_all('/(?:FROM|INTO|UPDATE) (\w+)/', implode("\n", preg_match_all('/```sql(.*?)```/s', $doc, $b) ? $b[1] : []) . "\n" . $doc, $u);
$usadas = array_values(array_unique(array_filter($u[1], fn ($x) => str_contains($x, '_'))));
$faltan = array_diff($usadas, array_keys($tablas));
ver('PRO-01', count($usadas) >= 5 && $faltan === [], sprintf('Las %d tablas de las consultas existen (%s)', count($usadas), implode(', ', $faltan) ?: implode(', ', $usadas)));
$cols = ['local_patients' => ['local_patient_id', 'code', 'given_name', 'family_name', 'sex'], 'vital_signs' => ['vital_sign_id', 'measured_at', 'heart_rate', 'oxygen_saturation', 'temperature', 'status', 'care_episode_id'],
         'beds' => ['bed_id', 'code', 'type', 'status'], 'sync_checkpoints' => ['last_event_id', 'last_occurred_at', 'lag_seconds'], 'central_inbox_messages' => ['source_node_id', 'event_id', 'event_type', 'payload_minimal', 'available_at']];
$malas = []; foreach ($cols as $tb => $cs) { foreach ($cs as $c) { if (!$tieneCol($tb, $c) || !str_contains($doc, "`$c`") && !str_contains($doc, $c)) { $malas[] = "$tb.$c"; } } }
ver('PRO-02', $malas === [], sprintf('Las %d columnas que devuelve o usa la propuesta existen en su tabla (faltan: %s)', array_sum(array_map('count', $cols)), implode(', ', $malas) ?: 'ninguna'));
ver('PRO-03', str_contains($tablas['central_inbox_messages'] ?? '', 'UNIQUE (source_node_id, event_id)') && str_contains($sql, 'ON central_inbox_messages (status, available_at)') && str_contains($sql, 'local_patients_code_unique UNIQUE (code)'),
    'Lo que la propuesta da por hecho existe: UNIQUE (source_node_id, event_id), índice (status, available_at) y código único del paciente');
ver('PRO-04', !preg_match('/TRIGGER \w+ \w+ (INSERT OR )?UPDATE[^;]* ON sync_checkpoints/', $sql) && !str_contains($tablas['sync_checkpoints'] ?? '', 'last_occurred_at >'), 'MV-01: ningún trigger ni CHECK impide que el checkpoint retroceda');
preg_match_all('/CREATE INDEX \w+ ON vital_signs \(([^)]*)\)/', $sql, $iv);
ver('PRO-05', in_array('care_episode_id', $iv[1], true) && !array_filter($iv[1], fn ($x) => str_contains($x, 'measured_at')), 'MV-02: vital_signs tiene índice en care_episode_id, pero ninguno con measured_at');
echo $fallas === 0 ? "\nResultado: la propuesta coincide con las migraciones.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
