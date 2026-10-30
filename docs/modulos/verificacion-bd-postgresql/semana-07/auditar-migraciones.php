<?php
declare(strict_types=1);

// Semana 7 — Auditoría estática de las migraciones CENTRAL y HOSPITAL: PK, FK con su ON DELETE, NOT NULL, UNIQUE y CHECK por migración,
// y los hallazgos AU-01..AU-05. Compara lo que mide con el informe. Solo PHP. Uso desde la raíz: php docs/modulos/verificacion-bd-postgresql/semana-07/auditar-migraciones.php
$raiz = dirname(__DIR__, 4); $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$informe = (string) @file_get_contents(__DIR__ . '/semana-07-auditoria-migraciones.md');
$medido = []; $cuerpos = [];
foreach (array_merge(glob("$raiz/database/migrations/central/*.php") ?: [], glob("$raiz/database/migrations/hospital/*.php") ?: []) as $f) {
    $sql = (string) file_get_contents($f);
    preg_match_all('/CREATE TABLE (\w+) \((.*?)(?=CREATE (?:TABLE|INDEX|UNIQUE|OR|TRIGGER|FUNCTION)|SQL\))/s', $sql, $t, PREG_SET_ORDER);
    foreach ($t as $x) { $cuerpos[$x[1]] = $x[2]; }
    $sinPk = array_filter($t, fn ($x) => !str_contains($x[2], 'PRIMARY KEY'));
    $medido[basename($f, '.php')] = [count($t), count($t) - count($sinPk), substr_count($sql, 'REFERENCES'), substr_count($sql, 'ON DELETE RESTRICT'),
        preg_match_all('/\bNOT NULL\b/', $sql), preg_match_all('/\bUNIQUE\b/', $sql), preg_match_all('/\bCHECK\b/', $sql)];
}
// Tabla del informe: | `migración` | tablas | con PK | FK | ON DELETE RESTRICT | NOT NULL | UNIQUE | CHECK | hallazgos |
preg_match_all('/^\| `(\d{4}_\d\d_\d\d_\d{6}_\w+)` \| (\d+) \| (\d+) \| (\d+) \| (\d+) \| (\d+) \| (\d+) \| (\d+) \|/m', $informe, $f, PREG_SET_ORDER);
$enInforme = []; foreach ($f as $x) { $enInforme[$x[1]] = array_map('intval', array_slice($x, 2, 7)); }
ksort($medido); ksort($enInforme);
$distintas = array_keys(array_filter($medido, fn ($v, $k) => ($enInforme[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
ver('AUD-01', count($medido) >= 11 && $distintas === [] && count($enInforme) === count($medido), sprintf('Las %d migraciones del informe tienen las mismas cuentas que el código (distintas: %s)', count($medido), implode(', ', $distintas) ?: 'ninguna'));
$t = array_sum(array_column($medido, 0)); $pk = array_sum(array_column($medido, 1)); $fk = array_sum(array_column($medido, 2)); $rs = array_sum(array_column($medido, 3));
ver('AUD-02', $t > 0 && $pk === $t && $fk === $rs, "Las $t tablas tienen PK y las $fk llaves foráneas declaran ON DELETE RESTRICT");
// Hallazgos
$au01 = array_filter(['care_episodes', 'care_assignments'], fn ($x) => preg_match("/IN \('closed', '(cancelled|revoked)'\) AND (ended_at|valid_to) > /", $cuerpos[$x] ?? ''));
ver('AU-01', count($au01) === 2, 'Cierre sin fecha de fin: el CHECK de periodo de care_episodes y care_assignments da NULL si la fecha falta, y PostgreSQL deja pasar la fila');
$vacias = [];
foreach (['local_patients' => ['code', 'given_name', 'family_name'], 'medical_records' => ['record_number'], 'doctor_profiles' => ['license_number'], 'specialties' => ['code', 'name'],
          'wards' => ['code', 'name'], 'beds' => ['code', 'type'], 'local_user_refs' => ['display_name', 'role_code']] as $tabla => $cols) {
    foreach ($cols as $c) { if (preg_match("/\b$c text NOT NULL\b/", $cuerpos[$tabla] ?? '') && !str_contains($cuerpos[$tabla], "btrim($c)")) { $vacias[] = "$tabla.$c"; } }
}
ver('AU-02', count($vacias) === 13, sprintf('%d columnas de texto obligatorias de HOSPITAL aceptan texto vacío (sin btrim): %s', count($vacias), implode(', ', $vacias)));
ver('AU-03', !str_contains($cuerpos['beds'] ?? '', 'type text NOT NULL CONSTRAINT') && !preg_match('/CHECK \(type IN/', $cuerpos['beds'] ?? ''), 'beds.type no tiene valores permitidos: las pruebas del grupo usan «standard» y el Micro-HIS de Traslados «general» y «uci»');
ver('AU-04', !preg_match('/role_code[^,]*CHECK/', ($cuerpos['local_user_refs'] ?? '') . ($cuerpos['hospital_memberships'] ?? '')), 'role_code es texto libre en local_user_refs y hospital_memberships (A-01 de la semana 6)');
$trigger = implode("\n", array_map('file_get_contents', glob("$raiz/database/migrations/hospital/*.php") ?: []));
ver('AU-05', !preg_match("/UPDATE beds SET status = 'occupied'/", $trigger), 'Ninguna migración pone la cama en occupied al abrir una estancia (H-01, RD-25)');
$p = (string) @file_get_contents(__DIR__ . '/propuesta/2026_10_30_000500_correct_hospital_integrity_gaps.php');
ver('COR-00', str_contains($p, 'function up') && str_contains($p, 'function down') && substr_count($p, 'not_blank_check') >= 2 && str_contains($p, 'bed_stays_occupy_bed'), 'La migración correctiva propuesta tiene up() y down() y cubre AU-01, AU-02 y AU-05');
echo $fallas === 0 ? "\nResultado: el informe coincide con las migraciones.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
