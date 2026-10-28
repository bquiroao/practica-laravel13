<?php

declare(strict_types=1);

/*
 * Semana 4 · Aplica database/integracion-central.sql sobre las tablas centrales REALES del proyecto
 * (se extraen de database/migrations/central/*.php, no se copian a mano) en un esquema temporal de
 * PostgreSQL, y comprueba lo que el análisis del repositorio compartido afirma.
 *   AUDITORIA_DB_DSN=… php bin/validar-integracion-central.php
 */

$dsn = getenv('AUDITORIA_DB_DSN') ?: exit("Defina AUDITORIA_DB_DSN.\n");
$pdo = new PDO($dsn, getenv('AUDITORIA_DB_USUARIO') ?: 'postgres', getenv('AUDITORIA_DB_CLAVE') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$esquema = 'central_' . getmypid();
$raiz = dirname(__DIR__, 5);

/** El SQL de up() de una migración del proyecto principal (bloque <<<'SQL' … SQL). */
$sqlDeMigracion = static function (string $archivo) use ($raiz): string {
    $codigo = (string) file_get_contents("$raiz/database/migrations/central/$archivo");
    preg_match("/<<<'SQL'\n(.*?)\n\s*SQL\)/s", $codigo, $m) || exit("No se encontró el SQL en $archivo\n");

    return $m[1];
};

$ok = static function (string $que, bool $cumple): void {
    printf("[%s] %s\n", $cumple ? 'OK  ' : 'FALLA', $que);
    $cumple || $GLOBALS['fallas'] = ($GLOBALS['fallas'] ?? 0) + 1;
};

try {
    $pdo->exec("CREATE SCHEMA $esquema; SET search_path TO $esquema, public");
    $pdo->exec('CREATE EXTENSION IF NOT EXISTS pgcrypto');
    foreach (['2026_08_11_000000_create_network_registry.php', '2026_08_11_000100_create_identity_and_memberships.php',
        '2026_08_11_000400_create_central_sync_tables.php'] as $migracion) {
        $pdo->exec($sqlDeMigracion($migracion));
        echo "aplicada la migración real $migracion" . PHP_EOL;
    }
    $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/integracion-central.sql'));
    echo 'aplicado database/integracion-central.sql' . PHP_EOL . PHP_EOL;

    $red = $pdo->query("INSERT INTO networks (name, timezone) VALUES ('Red demo', 'America/Guatemala') RETURNING network_id")->fetchColumn();
    $hospital = $pdo->query("INSERT INTO hospitals (network_id, code, name, timezone) VALUES ('$red', 'H1', 'Hospital demo', 'America/Guatemala') RETURNING hospital_id")->fetchColumn();
    $nodo = $pdo->query("INSERT INTO sync_nodes (hospital_id, name, schema_version, key_version) VALUES ('$hospital', 'nodo-h1', '1', '1') RETURNING node_id")->fetchColumn();
    $insertar = static fn (string $hospitalId, string $evento, int $secuencia): string => "INSERT INTO audit_logs (emisor, event_id, hospital_id, usuario_id, modulo, accion, ocurrido_en, resultado, secuencia, sello)
        VALUES ('svc-pacientes', '$evento', $hospitalId, gen_random_uuid(), 'pacientes', 'PACIENTE_REGISTRADO', now(), 'exito', $secuencia, repeat('a', 64))";

    $pdo->exec($insertar("'$hospital'", '00000000-0000-0000-0000-000000000001', 1));
    $ok('Un movimiento de un hospital registrado y de un usuario que NO está en central_users se acepta (sin FK de usuario)', true);
    try {
        $pdo->exec($insertar("'00000000-0000-0000-0000-0000000000ff'", '00000000-0000-0000-0000-000000000002', 1));
        $ok('Un hospital_id que no existe en hospitals se rechaza (FK)', false);
    } catch (PDOException $e) {
        $ok('Un hospital_id que no existe en hospitals se rechaza (FK, SQLSTATE ' . $e->errorInfo[0] . ')', $e->errorInfo[0] === '23503');
    }
    try {
        $pdo->exec("UPDATE audit_logs SET resultado = 'error'");
        $ok('UPDATE rechazado por el trigger', false);
    } catch (PDOException $e) {
        $ok('UPDATE rechazado por el trigger (solo inserción)', str_contains($e->getMessage(), 'solo inserción'));
    }
    try {
        $pdo->exec($insertar("'$hospital'", '00000000-0000-0000-0000-000000000001', 2));
        $ok('El mismo (emisor, event_id) se rechaza', false);
    } catch (PDOException $e) {
        $ok('El mismo (emisor, event_id) se rechaza (SQLSTATE ' . $e->errorInfo[0] . ')', $e->errorInfo[0] === '23505');
    }
    $pdo->exec("INSERT INTO central_inbox_messages (source_node_id, event_id, event_type, payload_minimal)
        VALUES ('$nodo', gen_random_uuid(), 'auditoria.movimiento', '{\"usuario\": \"u-ana\"}'),
               ('$nodo', gen_random_uuid(), 'paciente.vinculado', '{}')");
    $pendientes = $pdo->query('SELECT hospital_id, payload_minimal FROM auditoria_bandeja_pendiente')->fetchAll(PDO::FETCH_ASSOC);
    $ok('La bandeja compartida entrega solo los eventos «auditoria.movimiento», con el hospital del NODO de origen',
        count($pendientes) === 1 && $pendientes[0]['hospital_id'] === $hospital);
    $privilegios = (int) $pdo->query("SELECT count(*) FROM information_schema.role_table_grants WHERE table_schema = '$esquema'
        AND table_name = 'audit_logs' AND grantee = 'PUBLIC'")->fetchColumn();
    $ok('PUBLIC no tiene ningún privilegio sobre audit_logs', $privilegios === 0);
} finally {
    $pdo->exec("SET search_path TO public; DROP SCHEMA IF EXISTS $esquema CASCADE");
}
echo PHP_EOL . (($GLOBALS['fallas'] ?? 0) === 0 ? 'Todo lo que afirma el análisis se cumple.' : 'Hay ' . $GLOBALS['fallas'] . ' afirmaciones que no se cumplen.') . PHP_EOL;
exit(($GLOBALS['fallas'] ?? 0) === 0 ? 0 : 1);
