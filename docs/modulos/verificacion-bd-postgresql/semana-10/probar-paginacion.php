<?php
declare(strict_types=1);

// Semana 10 — Mide en PostgreSQL la paginación (OFFSET contra cursor), el tamaño de la respuesta y la sincronización con las tablas centrales.
// BORRA y recrea las bases CENTRAL y HOSPITAL de .env: exige bases que terminen en _test y el argumento --si-borrar-base-de-prueba.
// Uso desde la raíz del repo (con composer install): php docs/modulos/verificacion-bd-postgresql/semana-10/probar-paginacion.php --si-borrar-base-de-prueba
use Illuminate\Support\Facades\{Artisan, DB};

$raiz = dirname(__DIR__, 4);
require "$raiz/vendor/autoload.php";
(require "$raiz/bootstrap/app.php")->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$bases = [config('database.connections.central.database'), config('database.connections.hospital.database')];
if (!in_array('--si-borrar-base-de-prueba', $argv, true) || array_filter($bases, fn ($b) => !str_ends_with((string) $b, '_test'))) { exit('No se ejecutó: hacen falta bases *_test y --si-borrar-base-de-prueba (' . implode(', ', $bases) . ").\n"); }
$fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
foreach (['central', 'hospital'] as $c) { Artisan::call('migrate:fresh', ['--database' => $c, '--path' => database_path("migrations/$c"), '--realpath' => true, '--force' => true]); }
$h = DB::connection('hospital'); $c = DB::connection('central');

// 50 000 pacientes; la lista de admisión se ordena por código (índice único local_patients_code_unique).
$h->unprepared("INSERT INTO local_patients (global_id, code, given_name, family_name, birth_date, sex, contact)
    SELECT gen_random_uuid(), 'P-' || lpad(i::text, 6, '0'), 'Nombre' || i, 'Apellido' || (i % 997), date '1950-01-01' + (i % 25000), (ARRAY['female','male'])[1 + i % 2], 'tel ' || i FROM generate_series(1, 50000) i; ANALYZE local_patients");
$pagina = 2001; $tam = 20;
$offset = "SELECT local_patient_id, code, given_name, family_name, sex FROM local_patients ORDER BY code LIMIT $tam OFFSET " . (($pagina - 1) * $tam);
$ultimo = $h->scalar("SELECT code FROM local_patients ORDER BY code LIMIT 1 OFFSET " . (($pagina - 1) * $tam - 1));
$cursor = "SELECT local_patient_id, code, given_name, family_name, sex FROM local_patients WHERE code > '$ultimo' ORDER BY code LIMIT $tam";
ver('PAG-01', $h->select($offset) == $h->select($cursor), "Página $pagina (filas 40 001 a 40 020): OFFSET y cursor (code > '$ultimo') devuelven las mismas 20 filas");
$ms = function (string $sql) use ($h): float { $t = []; for ($i = 0; $i < 5; $i++) { preg_match('/Execution Time: ([\d.]+)/', implode("\n", array_column(array_map(fn ($r) => (array) $r, $h->select("EXPLAIN (ANALYZE) $sql")), 'QUERY PLAN')), $m); $t[] = (float) $m[1]; } sort($t); return $t[2]; };
[$a, $b] = [$ms($offset), $ms($cursor)];
ver('PAG-02', $b * 5 < $a, sprintf('Tiempo en la base (mediana de 5): OFFSET %.2f ms; cursor %.2f ms (%.0f veces más rápido)', $a, $b, $a / max($b, 0.001)));
$bytes = fn (string $sql) => (int) $h->scalar("SELECT octet_length(json_agg(x)::text) FROM ($sql) x");
[$completo, $reducido] = [$bytes("SELECT * FROM local_patients ORDER BY code LIMIT $tam"), $bytes("SELECT local_patient_id AS id, code, given_name || ' ' || family_name AS nombre, sex FROM local_patients ORDER BY code LIMIT $tam")];
ver('TAM-01', $reducido * 2 <= $completo, sprintf('Página de 20 pacientes en JSON: %d bytes con todas las columnas, %d con las 4 que usa la pantalla móvil (%.0f %% menos)', $completo, $reducido, 100 - 100 * $reducido / $completo));

// Sincronización: el hospital reenvía el mismo evento dos veces (se cortó la conexión antes de recibir la respuesta).
$c->unprepared("INSERT INTO networks (network_id, name, timezone) VALUES ('00000000-0000-0000-0000-0000000000a1', 'Red', 'America/Guatemala');
    INSERT INTO hospitals (hospital_id, network_id, code, name, timezone) VALUES ('00000000-0000-0000-0000-0000000000b1', '00000000-0000-0000-0000-0000000000a1', 'H1', 'Hospital 1', 'America/Guatemala');
    INSERT INTO sync_nodes (node_id, hospital_id, name, schema_version, key_version) VALUES ('00000000-0000-0000-0000-0000000000c1', '00000000-0000-0000-0000-0000000000b1', 'H1', '1', 'k1');
    INSERT INTO sync_checkpoints (node_id, channel, last_event_id, last_occurred_at) VALUES ('00000000-0000-0000-0000-0000000000c1', 'pacientes', gen_random_uuid(), '2026-10-29 10:00+00')");
$enviar = "INSERT INTO central_inbox_messages (source_node_id, event_id, event_type, payload_minimal) VALUES ('00000000-0000-0000-0000-0000000000c1', '00000000-0000-0000-0000-0000000000e1', 'patient.linked', '{\"local_patient_id\":\"x\"}') ON CONFLICT (source_node_id, event_id) DO NOTHING";
$n = $c->affectingStatement($enviar) + $c->affectingStatement($enviar);
ver('SYN-01', $n === 1 && (int) $c->scalar('SELECT count(*) FROM central_inbox_messages') === 1, 'Reenvío del mismo evento: 1 fila guardada y el segundo envío no hace nada (UNIQUE (source_node_id, event_id) + ON CONFLICT DO NOTHING)');
$viejo = "UPDATE sync_checkpoints SET last_occurred_at = '2026-10-29 09:00+00' WHERE node_id = '00000000-0000-0000-0000-0000000000c1' AND channel = 'pacientes'";
$c->beginTransaction(); $retrocede = $c->affectingStatement($viejo); $c->rollBack();
$seguro = $c->affectingStatement("$viejo AND (last_occurred_at IS NULL OR last_occurred_at < '2026-10-29 09:00+00')");
ver('SYN-02', $retrocede === 1 && $seguro === 0, 'Un lote viejo que llega tarde: el UPDATE simple hace retroceder el checkpoint (MV-01); el UPDATE condicional no lo mueve');
echo $fallas === 0 ? "\nResultado: la propuesta se comporta como dice el documento.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
