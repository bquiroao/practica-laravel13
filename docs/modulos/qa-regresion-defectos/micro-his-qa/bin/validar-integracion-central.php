<?php

declare(strict_types=1);

/*
 * Semana 4 · Aplica database/integracion-central.sql sobre las tablas REALES de la base central del proyecto
 * (el SQL se extrae de database/migrations/central/*.php, no se copia a mano) en un esquema temporal de PostgreSQL,
 * y comprueba lo que afirma el análisis del repositorio compartido. Deja todo como estaba al terminar.
 *   QA_DB_DSN=… php bin/validar-integracion-central.php
 */

$dsn = getenv('QA_DB_DSN') ?: exit("Defina QA_DB_DSN.\n");
$pdo = new PDO($dsn, getenv('QA_DB_USUARIO') ?: 'postgres', getenv('QA_DB_CLAVE') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sufijo = getmypid();
$esquema = "central_$sufijo";
$rol = "app_qa_$sufijo";
$raiz = dirname(__DIR__, 5);
$fallas = 0;

/** El SQL de up() de una migración del proyecto principal (bloque <<<'SQL' … SQL). */
$sqlDeMigracion = static function (string $archivo) use ($raiz): string {
    $codigo = (string) file_get_contents("$raiz/database/migrations/central/$archivo");
    preg_match("/<<<'SQL'\n(.*?)\n\s*SQL\)/s", $codigo, $m) || exit("No se encontró el SQL en $archivo\n");

    return $m[1];
};
$ok = static function (string $que, bool $cumple) use (&$fallas): void {
    printf("[%s] %s\n", $cumple ? 'OK  ' : 'FALLA', $que);
    $fallas += $cumple ? 0 : 1;
};
/** Ejecuta y devuelve null si funcionó, o el SQLSTATE y el mensaje si la base lo rechazó. */
$intentar = static function (string $sql) use ($pdo): ?string {
    try {
        $pdo->exec($sql);

        return null;
    } catch (PDOException $e) {
        return $e->errorInfo[0] . ' ' . ($e->errorInfo[2] ?? $e->getMessage());
    }
};
$valor = static fn (string $sql): string => (string) $pdo->query($sql)->fetchColumn();

try {
    $pdo->exec("CREATE SCHEMA $esquema; SET search_path TO $esquema, public; CREATE ROLE $rol NOLOGIN");
    // Solo C0 (red y hospitales) y C1 (identidad y membresías): es lo que el módulo QA necesita de la base central.
    foreach (['2026_08_11_000000_create_network_registry.php', '2026_08_11_000100_create_identity_and_memberships.php'] as $migracion) {
        $pdo->exec($sqlDeMigracion($migracion));
        echo "aplicada la migración real $migracion" . PHP_EOL;
    }
    $pdo->exec(str_replace('app_qa', $rol, (string) file_get_contents(dirname(__DIR__) . '/database/integracion-central.sql')));
    $pdo->exec("GRANT USAGE ON SCHEMA $esquema TO $rol");
    echo 'aplicado database/integracion-central.sql' . PHP_EOL . PHP_EOL;

    // Datos mínimos sobre las tablas reales: dos hospitales y personas con distintas membresías.
    $red = $valor("INSERT INTO networks (name, timezone) VALUES ('Red de prueba', 'America/Guatemala') RETURNING network_id");
    $h = static fn (string $code): string => $valor("INSERT INTO hospitals (network_id, code, name, timezone) VALUES ('$red', '$code', '$code', 'America/Guatemala') RETURNING hospital_id");
    [$norte, $sur] = [$h('HN'), $h('HS')];
    $u = static fn (string $s): string => $valor("INSERT INTO central_users (subject, email, display_name) VALUES ('$s', '$s@hospital.gt', '$s') RETURNING user_id");
    [$lider, $tester, $analista, $revocado, $vencido, $testerSur] = [$u('lider'), $u('tester'), $u('analista'), $u('revocado'), $u('vencido'), $u('tester-sur')];
    $m = static fn (string $user, string $hosp, string $rolHis, string $extra = '') => $pdo->exec("INSERT INTO hospital_memberships (user_id, hospital_id, role_code, valid_from"
        . ($extra === '' ? '' : ', status, valid_to') . ") VALUES ('$user', '$hosp', '$rolHis', now() - interval '30 days'" . ($extra === '' ? '' : ", $extra") . ')');
    $m($lider, $norte, 'qa_lead');
    $m($tester, $norte, 'qa_tester');
    $m($analista, $norte, 'qa_analyst');
    $m($revocado, $norte, 'qa_tester', "'revoked', NULL");
    $m($vencido, $norte, 'qa_tester', "'active', now() - interval '1 day'");
    $m($testerSur, $sur, 'qa_tester');

    $fks = (int) $valor("SELECT count(*) FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid JOIN pg_namespace n ON n.oid = t.relnamespace
        WHERE n.nspname = '$esquema' AND t.relname LIKE 'qa_%' AND c.contype = 'f' AND c.confrelid IN ('$esquema.hospitals'::regclass, '$esquema.central_users'::regclass)");
    $ok("Las tablas qa_* tienen FK reales a hospitals y central_users ($fks)", $fks === 6);

    $ok('El autor no puede ser su propio aprobador (CHECK)', $intentar("INSERT INTO qa_casos (hospital_id, caso_id, version, autor_id, aprobador_id, titulo, estado)
        VALUES ('$norte', 'TC-101', 1, '$lider', '$lider', 'C1', 'APROBADO')") !== null);
    $ok('Un analista (qa_analyst) no puede figurar como aprobador', str_starts_with((string) $intentar("INSERT INTO qa_casos (hospital_id, caso_id, version, autor_id, aprobador_id, titulo, estado)
        VALUES ('$norte', 'TC-101', 1, '$lider', '$analista', 'C1', 'APROBADO')"), '42501'));
    $ok('Un qa_lead activo planifica una campaña en su hospital', $intentar("INSERT INTO qa_campanas (hospital_id, nombre, lider_id) VALUES ('$norte', 'Regresión H4', '$lider')") === null);
    $ok('Un tester NO puede planificar (no es qa_lead)', str_starts_with((string) $intentar("INSERT INTO qa_campanas (hospital_id, nombre, lider_id) VALUES ('$norte', 'X', '$tester')"), '42501'));
    $campana = $valor("SELECT campana_id FROM qa_campanas WHERE hospital_id = '$norte'");
    $intento = static fn (string $quien, string $clave, int $n = 1): ?string => $intentar("INSERT INTO qa_intentos (hospital_id, campana_id, caso_id, numero, resultado, tester_id, idempotency_key)
        VALUES ('$norte', '$campana', 'TC-107', $n, 'PASA', '$quien', '$clave')");
    $ok('Un tester con membresía activa qa_tester registra un intento', $intento($tester, 'k-0001') === null);
    $ok('Un tester de OTRO hospital no registra en éste (42501)', str_starts_with((string) $intento($testerSur, 'k-0002', 2), '42501'));
    $ok('Una membresía revocada no sirve (42501)', str_starts_with((string) $intento($revocado, 'k-0003', 2), '42501'));
    $ok('Una membresía vencida (valid_to pasado) no sirve (42501)', str_starts_with((string) $intento($vencido, 'k-0004', 2), '42501'));
    $pdo->exec("UPDATE central_users SET status = 'inactive' WHERE user_id = '$tester'");
    $ok('Un usuario central inactivo pierde el acceso aunque su membresía siga activa', str_starts_with((string) $intento($tester, 'k-0005', 2), '42501'));
    $pdo->exec("UPDATE central_users SET status = 'active' WHERE user_id = '$tester'");
    $ok('La misma Idempotency-Key en el mismo hospital se rechaza (23505)', str_starts_with((string) $intento($tester, 'k-0001', 2), '23505'));
    $ok('UPDATE y DELETE de un intento se rechazan (solo agregar)', $intentar("UPDATE qa_intentos SET resultado = 'FALLA'") !== null && $intentar('DELETE FROM qa_intentos') !== null);

    $pdo->exec("SET ROLE $rol");
    $ok('El rol del módulo lee personas por la vista qa_usuarios (id, nombre, estado)', $intentar('SELECT user_id, display_name, status FROM qa_usuarios') === null);
    $ok('El rol del módulo NO lee central_users (correo, subject) (42501)', str_starts_with((string) $intentar('SELECT email FROM central_users'), '42501'));
    $ok('El rol del módulo NO modifica membresías (42501)', str_starts_with((string) $intentar("UPDATE hospital_memberships SET role_code = 'qa_lead'"), '42501'));
    $ok('El rol del módulo registra intentos por sí mismo (el trigger consulta la membresía con SECURITY DEFINER)', $intento($tester, 'k-0006', 2) === null);
    $ok('El rol del módulo no puede borrar intentos ni siquiera con privilegios de la tabla', $intentar('DELETE FROM qa_intentos') !== null);
    $pdo->exec('RESET ROLE');
    $publico = (int) $valor("SELECT count(*) FROM information_schema.role_table_grants WHERE grantee = 'PUBLIC' AND table_schema = '$esquema' AND table_name LIKE 'qa_%'");
    $ok('PUBLIC no tiene privilegios sobre las tablas qa_*', $publico === 0);
} finally {
    $pdo->exec('RESET ROLE');
    $pdo->exec("DROP SCHEMA IF EXISTS $esquema CASCADE");
    $pdo->exec("DROP ROLE IF EXISTS $rol");
}

echo PHP_EOL . ($fallas === 0 ? 'Todas las afirmaciones se cumplen.' : "$fallas afirmaciones NO se cumplen.") . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
