<?php

declare(strict_types=1);

use Qa\Aplicacion\Resultados\Salida;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Persistencia\EnMemoria\AlmacenEvidenciasEnMemoria;
use Qa\Persistencia\FabricaAdaptadores;
use Qa\Persistencia\Pdo\FabricaConexion;
use Qa\Persistencia\Pdo\PdoRegistroAuditoria;

/*
 * Integración con PostgreSQL real. Se omiten si QA_DB_DSN no está definido.
 *   QA_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_qa" php tests/ejecutar.php --solo=INT
 * Cada prueba reinstala el esquema «qa» y los datos de demostración (base de prueba desechable).
 */
require_once dirname(__DIR__, 2) . '/bin/instalar-base.php';

/** @return array{pdo: \PDO, uc: array<string, object>, almacen: AlmacenEvidenciasEnMemoria, auditoria: object} */
function baseDePrueba(?\Qa\Aplicacion\Puertos\RegistroAuditoria $auditoria = null): array
{
    $dsn = getenv('QA_DB_DSN');
    if ($dsn === false || $dsn === '') {
        omitir('QA_DB_DSN no definido');
    }
    $config = require dirname(__DIR__, 2) . '/config/config.php';
    $pdo = FabricaConexion::crear($dsn, $config['db']['usuario'], $config['db']['clave']);
    instalarBase($pdo);
    $p = FabricaAdaptadores::crear(['persistencia' => 'pdo'] + $config, $pdo);
    $p['almacen'] = new AlmacenEvidenciasEnMemoria();
    $p['reloj'] = new RelojFijo();
    if ($auditoria !== null) {
        $p['auditoria'] = $auditoria;
    }

    return ['pdo' => $pdo, 'uc' => construirCasosDeUso($p), 'almacen' => $p['almacen'], 'auditoria' => $p['auditoria']];
}

/** Planifica e inicia una campaña sobre «expediente» (TC-107, TC-108, TC-109). */
function campanaPg(array $b, array $casos = ['TC-107', 'TC-108', 'TC-109']): int
{
    $p = $b['uc']['planificar']->ejecutar('lider-qa', 'hosp-norte', ['nombre' => 'Regresión H4', 'funcionalidades' => ['expediente'], 'casos' => $casos]);
    $b['uc']['iniciar']->ejecutar('lider-qa', 'hosp-norte', $p->datos['id'], '1.4.0+abc1234');

    return $p->datos['id'];
}

function resultadoPg(array $b, int $campana, string $caso, string $resultado, array $extra = [], ?string $clave = null): Salida
{
    $datos = ['caso' => $caso, 'resultado' => $resultado];
    if (in_array($resultado, ['FALLA', 'BLOQUEADO'], true)) {
        $datos['evidencias'] = [['tipo' => 'captura', 'bytes' => Escenario::PNG . $caso]];
    }
    if ($resultado === 'FALLA') {
        $datos['defecto'] = ['titulo' => "Falla en $caso", 'pasos' => ['Ejecutar el caso'], 'severidad' => 'MAYOR'];
    }

    return $b['uc']['registrar']->ejecutar('tito-tester', 'hosp-norte', $campana, $clave ?? Escenario::clave(), array_merge($datos, $extra));
}

prueba('INT-01', 'Camino feliz en PostgreSQL: la campaña, sus intentos, la evidencia, el defecto y su historial quedan guardados', function (): void {
    $b = baseDePrueba();
    $id = campanaPg($b);
    resultadoPg($b, $id, 'TC-107', 'FALLA');
    resultadoPg($b, $id, 'TC-108', 'PASA');
    resultadoPg($b, $id, 'TC-109', 'NO_APLICA', ['motivo' => 'El build no toca signos vitales']);
    $b['uc']['clasificar']->ejecutar('lider-qa', 'hosp-norte', 1, ['severidad' => 'CRITICA', 'prioridad' => 'ALTA', 'motivo' => 'Edita una nota firmada']);
    $noGo = $b['uc']['cerrar']->ejecutar('lider-qa', 'hosp-norte', $id);
    igual(['NO_GO', 0.5], [$noGo->datos['decision'], $noGo->datos['tasa_aprobados']], 'NO_GO por el crítico y la tasa');
    $f = $b['pdo']->query("SELECT estado, decision, tasa_aprobados::text AS tasa, build FROM campanas WHERE id = $id")->fetch();
    igual(['CERRADA', 'NO_GO', '0.5000', '1.4.0+abc1234'], [$f['estado'], $f['decision'], $f['tasa'], $f['build']], 'fila de la campaña');
    igual([3, 1, 2], [(int) $b['pdo']->query('SELECT count(*) FROM intentos')->fetchColumn(), (int) $b['pdo']->query('SELECT count(*) FROM evidencias')->fetchColumn(),
        (int) $b['pdo']->query('SELECT count(*) FROM defecto_historial')->fetchColumn()], 'intentos, evidencias, historial');
    $t = $b['uc']['trazabilidad']->ejecutar('producto', 'hosp-norte', $id);
    igual(['FALLA', 'CRITICA', 'NO_APLICA'], [$t->datos['casos'][0]['ultimo_resultado'], $t->datos['casos'][0]['defectos'][0]['severidad'], $t->datos['casos'][2]['ultimo_resultado']], 'trazabilidad');
});

prueba('INT-02', 'R-03 con la matriz real: faltan los críticos de «expediente» y «agenda-camas»', function (): void {
    $b = baseDePrueba();
    $p = $b['uc']['planificar']->ejecutar('lider-qa', 'hosp-norte', ['nombre' => 'X', 'funcionalidades' => ['expediente', 'agenda-camas'], 'casos' => ['TC-109']]);
    $r = $b['uc']['iniciar']->ejecutar('lider-qa', 'hosp-norte', $p->datos['id'], '1.4.0');
    igual(['ALCANCE_INCOMPLETO', ['TC-106', 'TC-107', 'TC-108']], [$r->codigo, $r->datos['detalle']], 'faltan');
});

prueba('INT-03', 'R-11 en la base: UPDATE o DELETE de un intento, una evidencia o el historial se rechazan aunque se salten la aplicación', function (): void {
    $b = baseDePrueba();
    $id = campanaPg($b);
    resultadoPg($b, $id, 'TC-107', 'FALLA');
    foreach (["UPDATE intentos SET resultado = 'PASA'", 'DELETE FROM intentos', "UPDATE evidencias SET huella = repeat('0', 64)",
        "UPDATE defecto_historial SET evento = 'x'"] as $sql) {
        lanza(\PDOException::class, static fn () => $b['pdo']->exec($sql), $sql);
    }
    igual('FALLA', $b['pdo']->query('SELECT resultado FROM intentos')->fetchColumn(), 'el intento sigue igual');
});

prueba('INT-04', 'R-02 en la base: una versión aprobada no cambia, el autor no puede figurar como aprobador y nada se borra', function (): void {
    $b = baseDePrueba();
    lanza(\PDOException::class, static fn () => $b['pdo']->exec("UPDATE casos_prueba SET titulo = 'otro' WHERE caso_id = 'TC-101' AND tenant = 'hosp-norte'"), 'aprobada');
    lanza(\PDOException::class, static fn () => $b['pdo']->exec("UPDATE casos_prueba SET estado = 'APROBADO', aprobador = 'ana-qa' WHERE caso_id = 'TC-111'"), 'autor aprueba');
    lanza(\PDOException::class, static fn () => $b['pdo']->exec("DELETE FROM casos_prueba WHERE caso_id = 'TC-111'"), 'borrar');
    igual('CASO_APROBADO', $b['uc']['aprobar']->ejecutar('lider-qa', 'hosp-norte', 'TC-111')->codigo, 'la aprobación normal sí pasa');
});

prueba('INT-05', 'R-12: la clave de idempotencia es única en la base; el reintento devuelve el mismo intento', function (): void {
    $b = baseDePrueba();
    $id = campanaPg($b);
    $a = resultadoPg($b, $id, 'TC-107', 'PASA', [], 'pg-clave-0001');
    $r = resultadoPg($b, $id, 'TC-107', 'PASA', [], 'pg-clave-0001');
    igual([Salida::OK, $a->datos['intento']], [$r->tipo, $r->datos['intento']], 'repetición');
    lanza(\PDOException::class, static fn () => $b['pdo']->exec("INSERT INTO intentos (tenant, campana_id, caso_id, numero, resultado, tester, idempotency_key, huella_solicitud)
        VALUES ('hosp-norte', $id, 'TC-108', 1, 'PASA', 'x', 'pg-clave-0001', repeat('a', 64))"), 'UNIQUE');
});

prueba('INT-06', 'Atomicidad real: si la traza falla, PostgreSQL deshace el defecto y el intento (ROLLBACK)', function (): void {
    $falla = new class implements \Qa\Aplicacion\Puertos\RegistroAuditoria {
        public function registrar(string $accion, string $tenant, string $usuario, string $recurso): void
        {
            if ($accion === 'RESULTADO_REGISTRADO') {
                throw new ErrorPersistencia('bandeja llena');
            }
        }
    };
    $b = baseDePrueba($falla);
    $id = campanaPg($b);
    $r = resultadoPg($b, $id, 'TC-107', 'FALLA');
    igual('ERROR_PERSISTENCIA', $r->codigo, 'salida');
    igual([0, 0, 0], [(int) $b['pdo']->query('SELECT count(*) FROM intentos')->fetchColumn(), (int) $b['pdo']->query('SELECT count(*) FROM defectos')->fetchColumn(),
        (int) $b['pdo']->query('SELECT count(*) FROM evidencias')->fetchColumn()], 'nada a medias');
});

prueba('INT-07', 'Tenant: la campaña de hosp-norte no existe para hosp-sur, ni por id ni en la trazabilidad', function (): void {
    $b = baseDePrueba();
    $id = campanaPg($b);
    $sur = $b['uc']['registrar']->ejecutar('tester-sur', 'hosp-sur', $id, Escenario::clave(), ['caso' => 'TC-107', 'resultado' => 'PASA']);
    igual([Salida::NO_ENCONTRADO, Salida::NO_ENCONTRADO], [$sur->tipo, $b['uc']['trazabilidad']->ejecutar('tester-sur', 'hosp-sur', $id)->tipo], 'aislado');
});

prueba('INT-08', 'Concurrencia: dos cierres sobre la misma campaña, solo uno escribe (UPDATE condicionado al estado anterior)', function (): void {
    $b = baseDePrueba();
    $id = campanaPg($b, ['TC-107', 'TC-108']);
    resultadoPg($b, $id, 'TC-107', 'PASA');
    resultadoPg($b, $id, 'TC-108', 'PASA');
    $repo = new \Qa\Persistencia\Pdo\PdoRepositorioCampanas($b['pdo']);
    $vieja = $repo->porId('hosp-norte', $id);
    igual('CAMPANA_CERRADA', $b['uc']['cerrar']->ejecutar('lider-qa', 'hosp-norte', $id)->codigo, 'primero');
    $vieja->cerrar(0);
    igual('ESTADO_CAMBIO_CONCURRENTE', lanza(ReglaRechazada::class, static fn () => $repo->actualizarEstado($vieja), 'segundo')->codigo, 'segundo');
});

prueba('INT-09', 'La traza de auditoría va a la bandeja en la misma transacción, sin datos clínicos (solo identificadores)', function (): void {
    $b = baseDePrueba();
    $id = campanaPg($b);
    resultadoPg($b, $id, 'TC-107', 'FALLA');
    $filas = $b['pdo']->query('SELECT accion, actor, recurso FROM auditoria_bandeja ORDER BY id')->fetchAll();
    igual(['CAMPANA_PLANIFICADA', 'CAMPANA_INICIADA', 'RESULTADO_REGISTRADO'], array_column($filas, 'accion'), 'acciones');
    igual("campana:$id/caso:TC-107#1", $filas[2]['recurso'], 'recurso');
    afirmar($b['auditoria'] instanceof PdoRegistroAuditoria, 'adaptador real');
});

prueba('INT-10', 'datos-demo.php (memoria) y datos-demo.sql (PostgreSQL) describen los mismos casos y la misma matriz', function (): void {
    $b = baseDePrueba();
    $config = require dirname(__DIR__, 2) . '/config/config.php';
    $mem = FabricaAdaptadores::memoria($config, require dirname(__DIR__, 2) . '/database/datos-demo.php');
    $pg = FabricaAdaptadores::crear(['persistencia' => 'pdo'] + $config, $b['pdo']);
    $foto = static function (array $p): array {
        $r = [];
        foreach (['hosp-norte', 'hosp-sur'] as $t) {
            foreach ($p['casos']->variosPorId($t, array_map(static fn (int $n): string => "TC-$n", range(101, 111))) as $id => $c) {
                $r["$t|$id"] = [$c->titulo, $c->precondiciones, $c->pasos, $c->prioridad, $c->requisitos, $c->estado, $c->aprobador, $c->autor];
            }
            foreach (['identidad-central', 'mpi', 'consentimiento', 'sincronizacion', 'identidad-hospital', 'agenda-camas', 'expediente'] as $f) {
                $r["$t|matriz|$f"] = $p['casos']->criticosDe($t, [$f]);
            }
        }
        ksort($r);

        return $r;
    };
    $a = $foto($mem);
    igual([12, $a], [count(array_filter(array_keys($a), static fn (string $k): bool => !str_contains($k, '|matriz|'))), $foto($pg)], 'mismo contenido');
});
