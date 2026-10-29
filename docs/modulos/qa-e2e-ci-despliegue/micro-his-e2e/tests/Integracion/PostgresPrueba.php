<?php

declare(strict_types=1);

/*
 * Integración con PostgreSQL real (solo si E2E_DB_DSN está definido; la base se recrea: usar una de prueba).
 * Además del camino feliz por los adaptadores PDO, verifica que las reglas críticas se sostienen aunque alguien se salte la aplicación.
 */

use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Persistencia\Archivos\AlmacenEvidenciasEnDisco;
use E2e\Persistencia\Configuracion\RelojFijo;
use E2e\Persistencia\FabricaAdaptadores;
use E2e\Persistencia\Pdo\FabricaConexion;

require_once dirname(__DIR__, 2) . '/bin/instalar-base.php';

$conPostgres = static function (): array {
    $dsn = getenv('E2E_DB_DSN');
    if (!$dsn) {
        omitir('sin E2E_DB_DSN');
    }
    $config = require dirname(__DIR__, 2) . '/config/config.php';
    $pdo = FabricaConexion::crear($dsn, $config['db']['usuario'], $config['db']['clave']);
    instalarBase($pdo);
    $p = FabricaAdaptadores::crear(['persistencia' => 'pdo', 'evidencias' => sys_get_temp_dir() . '/e2e-int-' . getmypid()] + $config, $pdo);
    $p['reloj'] = new RelojFijo(new DateTimeImmutable('2026-10-29T14:00:00Z'));

    return [$pdo, $p, construirCasosDeUso($p, $config['politica_gate'])];
};
$falla = static function (PDO $pdo, string $sql, array $parametros = []): string {
    try {
        $pdo->prepare($sql)->execute($parametros);
    } catch (PDOException $e) {
        return $e->getMessage();
    }

    return 'NO FALLÓ';
};
$flujo = static function (array $uc): int {
    $uc['version']->ejecutar('pipeline-ci', 'hosp-norte', ['numero' => '2.4.0', 'commit' => 'c56f169', 'digest' => Escenario::DIGEST, 'modulos' => ['admision']]);
    $uc['plan']->ejecutar('ing-qa', 'hosp-norte', '2.4.0', ['E2E-101', 'E2E-102', 'E2E-103', 'E2E-104', 'E2E-105', 'E2E-106']);
    $id = $uc['iniciar']->ejecutar('pipeline-ci', 'hosp-norte', '2.4.0', Escenario::DIGEST, 'staging')->datos['id'];
    foreach (['E2E-101', 'E2E-102', 'E2E-103', 'E2E-104', 'E2E-105', 'E2E-106'] as $i => $esc) {
        $uc['reportar']->ejecutar('pipeline-ci', 'hosp-norte', $id, "int-clave-$i", ['escenario' => $esc, 'resultado' => 'PASA', 'intentos' => 1,
            'evidencias' => [['tipo' => 'junit', 'bytes' => "<testcase name=\"$esc\"/>"]]]);
    }
    $uc['cerrar']->ejecutar('pipeline-ci', 'hosp-norte', $id);

    return $id;
};

prueba('INT-01', 'Camino feliz en PostgreSQL: versión, plan congelado, ejecución, 6 resultados con evidencia en disco, gate APROBADO y traza', function () use ($conPostgres, $flujo): void {
    [$pdo, $p, $uc] = $conPostgres();
    $id = $flujo($uc);
    $g = $uc['gate']->ejecutar('resp-release', 'hosp-norte', '2.4.0', $id);
    igual(['APROBADO', []], [$g->datos['decision'], $g->datos['causas']], 'gate');
    $fila = $pdo->query("SELECT estado, plan_congelado FROM versiones WHERE numero = '2.4.0'")->fetch();
    igual(['APROBADA', true], [$fila['estado'], (bool) $fila['plan_congelado']], 'versión');
    igual([6, 6, 1, 11], array_map(static fn (string $t): int => (int) $pdo->query("SELECT count(*) FROM $t")->fetchColumn(), ['resultados', 'evidencias', 'evaluaciones', 'auditoria']),
        'filas');
    igual(6, count($uc['trazabilidad']->ejecutar('dev-ana', 'hosp-norte', '2.4.0')->datos['ejecuciones'][0]['resultados']), 'trazabilidad');
});

prueba('INT-02', 'R-04/R-08 en la base: resultados, evidencias, evaluaciones y traza no se modifican ni se borran, aunque se salten la aplicación', function () use ($conPostgres, $flujo, $falla): void {
    [$pdo, $p, $uc] = $conPostgres();
    $id = $flujo($uc);
    $uc['gate']->ejecutar('resp-release', 'hosp-norte', '2.4.0', $id);
    foreach (["UPDATE resultados SET resultado = 'PASA', intentos = 1", 'DELETE FROM evidencias', "UPDATE evaluaciones SET decision = 'APROBADO'", 'DELETE FROM auditoria'] as $sql) {
        afirmar(str_contains($falla($pdo, $sql), 'solo admite INSERT'), "no se bloqueó: $sql");
    }
});

prueba('INT-03', 'R-03 en la base: una ejecución con otro digest que su versión se rechaza; el digest de una versión no cambia', function () use ($conPostgres, $flujo, $falla): void {
    [$pdo, $p, $uc] = $conPostgres();
    $flujo($uc);
    $otro = 'sha256:' . str_repeat('0', 64);
    afirmar(str_contains($falla($pdo, "INSERT INTO ejecuciones (tenant, version, digest, entorno, plan, iniciada_en) VALUES ('hosp-norte', '2.4.0', ?, 'staging', '{}', now())", [$otro]),
        'otro digest que su versión'), 'insert con otro digest');
    afirmar(str_contains($falla($pdo, "UPDATE versiones SET digest = ? WHERE numero = '2.4.0'", [$otro]), 'no cambian'), 'cambiar el digest');
});

prueba('INT-04', 'R-02 en la base: el plan congelado no cambia ni se descongela; R-06: un PASA con 2 intentos no entra', function () use ($conPostgres, $flujo, $falla): void {
    [$pdo, $p, $uc] = $conPostgres();
    $id = $flujo($uc);
    afirmar(str_contains($falla($pdo, "UPDATE versiones SET plan = '{\"E2E-101\": true}' WHERE numero = '2.4.0'"), 'plan congelado'), 'cambiar el plan');
    afirmar(str_contains($falla($pdo, "UPDATE versiones SET plan_congelado = false WHERE numero = '2.4.0'"), 'plan congelado'), 'descongelar');
    afirmar(str_contains($falla($pdo, "INSERT INTO resultados (tenant, ejecucion_id, escenario_id, critico, resultado, intentos, clave, huella, reportado_en)
        VALUES ('hosp-norte', ?, 'E2E-107', false, 'PASA', 2, 'k-int-04', 'h', now())", [$id]), 'resultados_pasa_al_primero'), 'PASA con 2 intentos');
});

prueba('INT-05', 'R-12 en la base: dos resultados con la misma clave en el tenant chocan con el UNIQUE y el adaptador lo traduce a CLAVE_REUTILIZADA', function () use ($conPostgres): void {
    [$pdo, $p, $uc] = $conPostgres();
    $uc['version']->ejecutar('pipeline-ci', 'hosp-norte', ['numero' => '2.4.0', 'commit' => 'c56f169', 'digest' => Escenario::DIGEST, 'modulos' => ['admision']]);
    $uc['plan']->ejecutar('ing-qa', 'hosp-norte', '2.4.0', ['E2E-101', 'E2E-102']);
    $id = $uc['iniciar']->ejecutar('pipeline-ci', 'hosp-norte', '2.4.0', Escenario::DIGEST, 'staging')->datos['id'];
    $r = static fn (string $esc) => \E2e\Dominio\Modelo\ResultadoEscenario::desde($esc, true, 'PASA', 1, [], 'misma-clave-01', 'h', new DateTimeImmutable());
    $p['ejecuciones']->agregarResultado('hosp-norte', $id, $r('E2E-101'));
    igual('CLAVE_REUTILIZADA', lanza(ReglaRechazada::class, static fn () => $p['ejecuciones']->agregarResultado('hosp-norte', $id, $r('E2E-102')), 'carrera')->codigo, 'código');
});

prueba('INT-06', 'Atomicidad real: si el trabajo falla después de escribir el resultado, PostgreSQL deshace el resultado y su evidencia', function () use ($conPostgres): void {
    [$pdo, $p, $uc] = $conPostgres();
    $uc['version']->ejecutar('pipeline-ci', 'hosp-norte', ['numero' => '2.4.0', 'commit' => 'c56f169', 'digest' => Escenario::DIGEST, 'modulos' => ['admision']]);
    $uc['plan']->ejecutar('ing-qa', 'hosp-norte', '2.4.0', ['E2E-101', 'E2E-102']);
    $id = $uc['iniciar']->ejecutar('pipeline-ci', 'hosp-norte', '2.4.0', Escenario::DIGEST, 'staging')->datos['id'];
    $ev = (new AlmacenEvidenciasEnDisco(sys_get_temp_dir() . '/e2e-int-' . getmypid()))->guardar('log', 'log');
    lanza(RuntimeException::class, static fn () => $p['transaccion']->ejecutar(static function () use ($p, $id, $ev): void {
        $p['ejecuciones']->agregarResultado('hosp-norte', $id, \E2e\Dominio\Modelo\ResultadoEscenario::desde('E2E-101', true, 'FALLA', 1, [$ev], 'k-atomica-1', 'h', new DateTimeImmutable()));
        throw new RuntimeException('la traza falló');
    }), 'trabajo');
    igual([0, 0], [(int) $pdo->query('SELECT count(*) FROM resultados')->fetchColumn(), (int) $pdo->query('SELECT count(*) FROM evidencias')->fetchColumn()], 'nada escrito');
});

prueba('INT-07', 'Tenant: la versión de hosp-norte no existe para hosp-sur; y datos-demo.php y datos-demo.sql describen el mismo catálogo', function () use ($conPostgres, $flujo): void {
    [$pdo, $p, $uc] = $conPostgres();
    $flujo($uc);
    igual('NO_ENCONTRADO', $uc['trazabilidad']->ejecutar('pipeline-ci', 'hosp-sur', '2.4.0')->codigo, 'otro tenant');
    $sql = array_map(static fn (array $f): array => [$f['tenant'], $f['escenario_id'], $f['titulo'], $f['modulo'], (bool) $f['critico'], json_decode($f['requisitos'], true), $f['datos']],
        $pdo->query('SELECT * FROM escenarios ORDER BY tenant, escenario_id')->fetchAll());
    $php = (require dirname(__DIR__, 2) . '/database/datos-demo.php')['escenarios'];
    usort($php, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
    igual($php, $sql, 'catálogo');
});

prueba('INT-08', 'Repositorio compartido: cada evaluación del gate se publica en compartido.calidad_versiones en la MISMA transacción, y el hecho no se corrige', function () use ($conPostgres, $flujo, $falla): void {
    [$pdo, $p, $uc] = $conPostgres();
    $pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/integracion-compartida.sql'));
    $id = $flujo($uc);
    $uc['gate']->ejecutar('resp-release', 'hosp-norte', '2.4.0', $id);
    $fila = $pdo->query('SELECT tenant, version, digest, decision FROM compartido.calidad_versiones')->fetchAll();
    igual([['tenant' => 'hosp-norte', 'version' => '2.4.0', 'digest' => Escenario::DIGEST, 'decision' => 'APROBADO']], $fila, 'hecho publicado');
    afirmar(str_contains($falla($pdo, "UPDATE compartido.calidad_versiones SET decision = 'RECHAZADO'"), 'solo admite INSERT'), 'el hecho se pudo corregir');
    // Si la evaluación se deshace, el hecho también: es la misma transacción.
    $pdo->beginTransaction();
    $pdo->exec("INSERT INTO e2e.evaluaciones (tenant, version, ejecucion_id, decision, reglas, causas, digest, politica, evaluada_en, evaluada_por)
        SELECT tenant, version, ejecucion_id, 'RECHAZADO', reglas, '[\"x\"]', digest, politica, now(), 'u' FROM e2e.evaluaciones LIMIT 1");
    $pdo->rollBack();
    igual(1, (int) $pdo->query('SELECT count(*) FROM compartido.calidad_versiones')->fetchColumn(), 'sin hecho huérfano');
});

prueba('INT-09', 'Repositorio compartido: el rol lector ve SOLO su hospital (seguridad por filas), no escribe y no entra al esquema e2e', function () use ($conPostgres, $flujo, $falla): void {
    [$pdo, $p, $uc] = $conPostgres();
    $pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/integracion-compartida.sql'));
    $uc['gate']->ejecutar('resp-release', 'hosp-norte', '2.4.0', $flujo($uc));
    $pdo->exec('SET ROLE his_lector_calidad');
    try {
        $ver = static function (string $tenant) use ($pdo): int {
            $pdo->exec("SET his.tenant = '$tenant'");

            return (int) $pdo->query('SELECT count(*) FROM compartido.calidad_versiones')->fetchColumn();
        };
        igual([1, 0], [$ver('hosp-norte'), $ver('hosp-sur')], 'filas por tenant');
        afirmar(str_contains($falla($pdo, "INSERT INTO compartido.calidad_versiones (tenant, version, digest, decision, causas, politica, evaluada_en)
            VALUES ('hosp-norte', '9.9.9', 'sha256:' || repeat('0', 64), 'APROBADO', '[]', 'p', now())"), 'permission denied'), 'el lector pudo escribir');
        afirmar(str_contains($falla($pdo, 'SELECT * FROM e2e.evaluaciones'), 'permission denied'), 'el lector entró al esquema e2e');
    } finally {
        $pdo->exec('RESET ROLE');
        $pdo->exec('RESET his.tenant');
    }
});
