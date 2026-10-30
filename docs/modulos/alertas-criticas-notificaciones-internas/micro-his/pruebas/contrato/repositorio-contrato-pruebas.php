<?php
/**
 * Prueba de CONTRATO de AlertaRepository: las mismas pruebas corren contra InMemory y contra PDO.
 * Si un adaptador se comporta distinto, falla aquí. PDO usa la base micro_alertas_test.
 * @var Pruebas $t
 */
declare(strict_types=1);

require_once __DIR__ . '/../dobles/escenario.php';

use AlertasCriticas\Domain\{Alerta, AlertaRepository, ErrorPersistenciaException, EstadoAlerta};
use AlertasCriticas\Persistence\{ConexionPdo, InMemoryAlertaRepository, PdoAlertaRepository};

$config = require __DIR__ . '/../../config.php';
$adaptadores = [
    'InMemory' => fn (): AlertaRepository => new InMemoryAlertaRepository(),
    'PDO' => function () use ($config): AlertaRepository {
        $pdo = ConexionPdo::crear($config, $config['base_prueba']);
        $pdo->exec('TRUNCATE alerta_evento, alerta_destinatario, alerta_critica');
        return new PdoAlertaRepository($pdo);
    },
];
$h = fn (string $hora) => new DateTimeImmutable("2026-10-29 $hora:00", new DateTimeZone('America/Guatemala'));
$nueva = function (array $cambios = [], string $hora = '10:00') use ($h): Alerta {
    $a = Alerta::crear(datosAlerta($cambios), $h($hora));
    $a->registrarEnvio(['med.tratante01', 'enf.turno01'], $h($hora));
    return $a;
};
$resumen = fn (?Alerta $a) => $a === null ? null : [$a->id, $a->estado()->value, $a->nivel(), $a->destinatarios(),
    array_map(fn ($e) => [$e->orden, $e->evento, $e->actor, $e->momento->format('Y-m-d H:i'), $e->detalle], $a->historial())];

foreach ($adaptadores as $nombre => $fabrica) {
    $t->prueba("[$nombre] guardar y buscar devuelve la misma alerta", function () use ($t, $fabrica, $nueva, $resumen) {
        $repo = $fabrica();
        $a = $nueva();
        $repo->guardar($a);
        $leida = $repo->buscar($a->id, 'HOSP-GUA-01');
        $t->igual($resumen($a)[4], $resumen($leida)[4]);
        $t->igual(['enf.turno01' => 0, 'med.tratante01' => 0], $leida->destinatarios(), 'Orden por nivel y usuario');
    });

    $t->prueba("[$nombre] RNF-01 otro tenant, id inexistente o inválido devuelven null", function () use ($t, $fabrica, $nueva) {
        $repo = $fabrica();
        $a = $nueva();
        $repo->guardar($a);
        $t->igual([null, null, null], [$repo->buscar($a->id, 'HOSP-XEL-02'),
            $repo->buscar('00000000-0000-4000-8000-000000000000', 'HOSP-GUA-01'), $repo->buscar('no-es-uuid', 'HOSP-GUA-01')]);
    });

    $t->prueba("[$nombre] buscarPorOrigen encuentra por tenant + origen + id de origen", function () use ($t, $fabrica, $nueva) {
        $repo = $fabrica();
        $a = $nueva();
        $repo->guardar($a);
        $t->igual($a->id, $repo->buscarPorOrigen('HOSP-GUA-01', 'ingreso-resultados-laboratorio', 'LAB-77801')?->id);
        $t->igual(null, $repo->buscarPorOrigen('HOSP-XEL-02', 'ingreso-resultados-laboratorio', 'LAB-77801'));
    });

    $t->prueba("[$nombre] RN-02 otro id con el mismo origen lanza ErrorPersistenciaException", function () use ($t, $fabrica, $nueva) {
        $repo = $fabrica();
        $repo->guardar($nueva());
        $t->lanza(ErrorPersistenciaException::class, fn () => $repo->guardar($nueva([], '10:01')));
    });

    $t->prueba("[$nombre] guardar de nuevo actualiza el estado sin duplicar eventos", function () use ($t, $fabrica, $nueva, $h) {
        $repo = $fabrica();
        $a = $nueva();
        $repo->guardar($a);
        $a->acusar('enf.turno01', $h('10:05'));
        $repo->guardar($a);
        $repo->guardar($a);
        $leida = $repo->buscar($a->id, 'HOSP-GUA-01');
        $t->igual([EstadoAlerta::ACUSADA, ['creada', 'enviada', 'acusada']], [$leida->estado(), eventos($leida)]);
    });

    $t->prueba("[$nombre] lo leído es una copia: cambios sin guardar no se ven", function () use ($t, $fabrica, $nueva, $h) {
        $repo = $fabrica();
        $a = $nueva();
        $repo->guardar($a);
        $repo->buscar($a->id, 'HOSP-GUA-01')->acusar('enf.turno01', $h('10:05'));
        $t->igual(EstadoAlerta::ENVIADA, $repo->buscar($a->id, 'HOSP-GUA-01')->estado());
    });

    $t->prueba("[$nombre] pendientesDeAcuse excluye acusadas y agotadas", function () use ($t, $fabrica, $nueva, $h) {
        $repo = $fabrica();
        $pendiente = $nueva(['id_origen' => 'LAB-1']);
        $acusada = $nueva(['id_origen' => 'LAB-2']);
        $acusada->acusar('enf.turno01', $h('10:05'));
        $agotada = $nueva(['id_origen' => 'LAB-3']);
        $agotada->agotarEscalamiento($h('10:15'));
        foreach ([$pendiente, $acusada, $agotada] as $a) {
            $repo->guardar($a);
        }
        $t->igual([$pendiente->id], array_map(fn ($a) => $a->id, $repo->pendientesDeAcuse()));
    });

    $t->prueba("[$nombre] listar trae solo el tenant, más recientes primero", function () use ($t, $fabrica, $nueva) {
        $repo = $fabrica();
        $vieja = $nueva(['id_origen' => 'LAB-1'], '09:00');
        $reciente = $nueva(['id_origen' => 'LAB-2'], '11:00');
        $otro = $nueva(['id_origen' => 'LAB-3', 'tenant' => 'HOSP-XEL-02']);
        foreach ([$vieja, $reciente, $otro] as $a) {
            $repo->guardar($a);
        }
        $t->igual([$reciente->id, $vieja->id], array_map(fn ($a) => $a->id, $repo->listar('HOSP-GUA-01')));
    });
}
