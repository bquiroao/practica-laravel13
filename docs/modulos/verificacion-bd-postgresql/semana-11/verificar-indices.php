<?php
declare(strict_types=1);

// Semana 11 — Comprueba la migración de índices propuesta contra las migraciones del grupo. Solo PHP.
// Uso desde la raíz del repo: php docs/modulos/verificacion-bd-postgresql/semana-11/verificar-indices.php
$raiz = dirname(__DIR__, 4); $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$mig = (string) @file_get_contents(__DIR__ . '/propuesta/2026_10_30_000600_add_frequent_query_indexes.php');
$doc = (string) @file_get_contents(__DIR__ . '/semana-11-consultas-frecuentes-indices.md');
$sql = implode("\n", array_map('file_get_contents', glob("$raiz/database/migrations/hospital/*.php") ?: []));
preg_match_all("/'(\w+_index)' => \"?'?(\w+) \(([^)]*\)?[^'\"]*)/", $mig, $ix, PREG_SET_ORDER);
ver('IX-01', count($ix) === 4 && !array_filter($ix, fn ($x) => str_contains($sql, $x[1])), sprintf('La migración propone %d índices y ninguno existe ya en las migraciones de HOSPITAL', count($ix)));
$faltan = [];
foreach ($ix as $x) {
    preg_match('/CREATE TABLE ' . $x[2] . ' \((.*?)(?=CREATE (?:TABLE|INDEX|UNIQUE|OR|TRIGGER|FUNCTION)|SQL\))/s', $sql, $t);
    preg_match_all('/\b([a-z_]+)\b(?= (?:DESC|text_pattern_ops)|,|\)|$)/', preg_replace('/WHERE.*/', '', $x[3]), $c);
    foreach (array_diff($c[1], ['lower', 'text_pattern_ops', 'DESC']) as $col) { if (!preg_match("/(^|[\\s(,])$col (uuid|text|timestamptz|date)/", $t[1] ?? '')) { $faltan[] = "$x[2].$col"; } }
}
ver('IX-02', $faltan === [], sprintf('Las columnas de los 4 índices existen en sus tablas (faltan: %s)', implode(', ', $faltan) ?: 'ninguna'));
ver('IX-03', !array_filter($ix, fn ($x) => !str_contains($doc, "`$x[1]`")), 'El documento justifica y mide los mismos 4 índices de la migración');
ver('IX-04', str_contains($mig, '$withinTransaction = false') && substr_count($mig, 'CONCURRENTLY') >= 2 && str_contains($mig, 'function down'), 'La migración crea con CONCURRENTLY fuera de transacción y su down() los quita');
ver('IX-05', str_contains($sql, 'CREATE INDEX vital_signs_care_episode_id_index ON vital_signs (care_episode_id);') && str_contains($sql, 'CREATE INDEX appointments_doctor_profile_id_index ON appointments (doctor_profile_id);'),
    'Punto de partida medido: hoy C3 y C4 solo tienen índices de una columna (care_episode_id, doctor_profile_id)');
echo $fallas === 0 ? "\nResultado: la propuesta de índices coincide con las migraciones.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
