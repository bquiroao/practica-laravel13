<?php
/**
 * Integración con la base de prueba micro_alertas_test (PostgreSQL 16, puerto 55444).
 * Requiere: docker compose up -d  y  php scripts/crear-esquema.php --prueba
 * @var Pruebas $t
 */
declare(strict_types=1);

require_once __DIR__ . '/../dobles/escenario.php';

use AlertasCriticas\Domain\{ErrorPersistenciaException, EstadoAlerta, Alerta};
use AlertasCriticas\Persistence\{ConexionPdo, PdoAlertaRepository};

$config = require __DIR__ . '/../../config.php';
$conectar = function () use ($config): PDO {
    $pdo = ConexionPdo::crear($config, $config['base_prueba']);
    $pdo->exec('TRUNCATE alerta_evento, alerta_destinatario, alerta_critica');
    return $pdo;
};

$t->prueba('CAMINO FELIZ con base de prueba: registrar y releer igual', function () use ($t, $conectar) {
    $pdo = $conectar();
    $e = escenario(new PdoAlertaRepository($pdo));
    $id = $e['registrar']->ejecutar(datosAlerta())['alerta']->id;
    $leida = (new PdoAlertaRepository($pdo))->buscar($id, 'HOSP-GUA-01');
    $t->igual([EstadoAlerta::ENVIADA, 0, '10:00'], [$leida->estado(), $leida->nivel(), $leida->plazoDesde()->format('H:i')]);
    $t->igual(['enf.turno01' => 0, 'med.tratante01' => 0], $leida->destinatarios());
    $t->igual(['creada', 'enviada'], eventos($leida));
    $t->igual('PAC-00451', $leida->paciente->valor);
});

$t->prueba('CA-03 RN-02 en base: el segundo registro devuelve la misma alerta', function () use ($t, $conectar) {
    $pdo = $conectar();
    $e = escenario(new PdoAlertaRepository($pdo));
    $a = $e['registrar']->ejecutar(datosAlerta())['alerta'];
    $r = $e['registrar']->ejecutar(datosAlerta());
    $t->igual([true, $a->id, 1], [$r['duplicada'], $r['alerta']->id,
        (int) $pdo->query('SELECT count(*) FROM alerta_critica')->fetchColumn()]);
});

$t->prueba('RN-02 la restricción única de la base respalda la deduplicación', function () use ($t, $conectar) {
    $repo = new PdoAlertaRepository($conectar());
    $repo->guardar(Alerta::crear(datosAlerta(), new DateTimeImmutable('2026-10-29 10:00:00')));
    $t->lanza(ErrorPersistenciaException::class,
        fn () => $repo->guardar(Alerta::crear(datosAlerta(), new DateTimeImmutable('2026-10-29 10:01:00'))));
});

$t->prueba('CA-08 acuse idempotente persistido: un solo evento «acusada» en la base', function () use ($t, $conectar) {
    $pdo = $conectar();
    $e = escenario(new PdoAlertaRepository($pdo));
    $id = $e['registrar']->ejecutar(datosAlerta())['alerta']->id;
    $e['acusar']->ejecutar($id, 'enf.turno01', 'HOSP-GUA-01');
    $e['acusar']->ejecutar($id, 'enf.turno01', 'HOSP-GUA-01');
    $st = $pdo->prepare("SELECT count(*) FROM alerta_evento WHERE alerta_id = :id AND evento = 'acusada'");
    $st->execute([':id' => $id]);
    $t->igual(1, (int) $st->fetchColumn());
});

$t->prueba('RNF-01 buscar desde otro tenant o con id no válido devuelve null', function () use ($t, $conectar) {
    $repo = new PdoAlertaRepository($conectar());
    $id = escenario($repo)['registrar']->ejecutar(datosAlerta())['alerta']->id;
    $t->igual([null, null], [$repo->buscar($id, 'HOSP-XEL-02'), $repo->buscar("x' OR '1'='1", 'HOSP-GUA-01')]);
});

$t->prueba('CA-10 escalamiento con reloj fijo y base real', function () use ($t, $conectar) {
    $repo = new PdoAlertaRepository($conectar());
    $e = escenario($repo);
    $id = $e['registrar']->ejecutar(datosAlerta())['alerta']->id;
    $e['reloj']->avanzarMinutos(15);
    $t->igual(['escaladas' => 1, 'agotadas' => 0], $e['escalar']->ejecutar());
    $leida = $repo->buscar($id, 'HOSP-GUA-01');
    $t->igual([1, EstadoAlerta::ESCALADA, ['enf.turno01' => 0, 'med.tratante01' => 0, 'jefe.servicio01' => 1]],
        [$leida->nivel(), $leida->estado(), $leida->destinatarios()]);
});

$t->prueba('RN-10 la base rechaza modificar o borrar el historial', function () use ($t, $conectar) {
    $pdo = $conectar();
    escenario(new PdoAlertaRepository($pdo))['registrar']->ejecutar(datosAlerta());
    $t->lanza(PDOException::class, fn () => $pdo->exec("UPDATE alerta_evento SET actor = 'otro'"));
    $t->lanza(PDOException::class, fn () => $pdo->exec('DELETE FROM alerta_evento'));
});

$t->prueba('ERROR DE PERSISTENCIA real: sin tablas visibles se lanza ErrorPersistenciaException', function () use ($t, $conectar) {
    $pdo = $conectar();
    $pdo->exec('SET search_path TO esquema_que_no_existe');
    $e = escenario(new PdoAlertaRepository($pdo));
    $t->lanza(ErrorPersistenciaException::class, fn () => $e['registrar']->ejecutar(datosAlerta()));
    $t->igual([], $e['notificador']->enviados);
});
