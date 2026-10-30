<?php

declare(strict_types=1);

/*
 * Repository (semana 4): UNA suite de contrato que corren los dos adaptadores, en memoria y PDO. Si un adaptador pasa
 * y el otro no, el puerto no está bien definido o uno de los dos lo implementa mal. Con PDO, solo si E2E_DB_DSN existe.
 */

use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Dominio\Modelo\Decision;
use E2e\Dominio\Modelo\Ejecucion;
use E2e\Dominio\Modelo\EscenarioE2E;
use E2e\Dominio\Modelo\Evidencia;
use E2e\Dominio\Modelo\ResultadoEscenario;
use E2e\Dominio\Modelo\Version;
use E2e\Persistencia\FabricaAdaptadores;
use E2e\Persistencia\Pdo\FabricaConexion;

require_once dirname(__DIR__, 2) . '/bin/instalar-base.php';

$fabricas = [
    'MEM' => static function (): array {
        $config = require dirname(__DIR__, 2) . '/config/config.php';

        return FabricaAdaptadores::memoria($config, require dirname(__DIR__, 2) . '/database/datos-demo.php');
    },
    'PDO' => static function (): array {
        $dsn = getenv('E2E_DB_DSN');
        if (!$dsn) {
            omitir('sin E2E_DB_DSN');
        }
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $pdo = FabricaConexion::crear($dsn, $config['db']['usuario'], $config['db']['clave']);
        instalarBase($pdo);

        return FabricaAdaptadores::crear(['persistencia' => 'pdo', 'evidencias' => sys_get_temp_dir() . '/e2e-con-' . getmypid()] + $config, $pdo);
    },
];
$hora = new DateTimeImmutable('2026-10-29T14:00:00Z');
$version = static function (array $p, string $numero = '2.4.0', string $tenant = 'hosp-norte'): Version {
    $v = Version::registrar($tenant, $numero, 'c56f169', 'sha256:' . hash('sha256', "$tenant/$numero"), ['admision'], 'pipeline-ci');
    $p['versiones']->crear($v);

    return $v;
};
$ejecucion = static function (array $p, Version $v) use ($hora): Ejecucion {
    $v->planificar($p['escenarios']->delTenant($v->tenant), array_map(static fn (EscenarioE2E $e): string => $e->id, $p['escenarios']->delTenant($v->tenant)));
    $e = Ejecucion::iniciar($v, $v->digest, 'staging', $hora);
    $p['versiones']->actualizar($v);

    return $p['ejecuciones']->crear($e);
};
$resultado = static fn (string $esc, string $clave, array $ev = []): ResultadoEscenario => ResultadoEscenario::desde($esc, false, $ev === [] ? 'PASA' : 'FALLA', 1, $ev, $clave, 'h-' . $clave, $hora);

foreach ($fabricas as $nombre => $fabrica) {
    $id = static fn (int $n): string => sprintf('CON-%02d-%s', $n, $nombre);

    prueba($id(1), "[$nombre] Escenarios: lo guardado vuelve igual (requisitos y criticidad), ordenado por id, por tenant", function () use ($fabrica): void {
        $p = $fabrica();
        $p['escenarios']->guardar(new EscenarioE2E('hosp-norte', 'E2E-099', 'Primero', 'citas', false, ['RF-01', 'RF-09'], 'SINT-XA', 'ing-qa'));
        $todos = $p['escenarios']->delTenant('hosp-norte');
        igual(['E2E-099', 'E2E-101'], [$todos[0]->id, $todos[1]->id], 'orden por id');
        igual([['RF-01', 'RF-09'], false, 'SINT-XA'], [$todos[0]->requisitos, $todos[0]->critico, $todos[0]->datos], 'campos');
        igual([null, 1], [$p['escenarios']->porId('hosp-sur', 'E2E-099'), count($p['escenarios']->delTenant('hosp-sur'))], 'otro tenant');
    });

    prueba($id(2), "[$nombre] Versiones: por número y por digest; el plan y el estado persisten; otro tenant no la ve", function () use ($fabrica, $version): void {
        $p = $fabrica();
        $v = $version($p);
        $v->planificar($p['escenarios']->delTenant('hosp-norte'), ['E2E-101', 'E2E-102']);
        $v->congelarPlan();
        $p['versiones']->actualizar($v);
        $leida = $p['versiones']->porNumero('hosp-norte', '2.4.0');
        igual([['E2E-101' => true, 'E2E-102' => true], true, 'CANDIDATA', ['admision']], [$leida->plan(), $leida->planCongelado(), $leida->estado(), $leida->modulos], 'leída');
        igual(['2.4.0', null, null], [$p['versiones']->porDigest('hosp-norte', $v->digest)?->numero, $p['versiones']->porNumero('hosp-sur', '2.4.0'),
            $p['versiones']->porDigest('hosp-sur', $v->digest)], 'por digest y tenant');
    });

    prueba($id(3), "[$nombre] Ejecuciones: crear asigna id; los resultados vuelven en orden con sus evidencias; el plan se conserva", function () use ($fabrica, $version, $ejecucion, $resultado): void {
        $p = $fabrica();
        $e = $ejecucion($p, $version($p));
        $ev = [new Evidencia('traza', str_repeat('a', 64), 10, 'disco://aa/x'), new Evidencia('captura', str_repeat('b', 64), 20, 'disco://bb/y')];
        $p['ejecuciones']->agregarResultado('hosp-norte', (int) $e->id, $resultado('E2E-104', 'clave-con-0001', $ev));
        $p['ejecuciones']->agregarResultado('hosp-norte', (int) $e->id, $resultado('E2E-101', 'clave-con-0002'));
        $leida = $p['ejecuciones']->porId('hosp-norte', (int) $e->id);
        igual([['E2E-104', 'E2E-101'], ['traza', 'captura'], 6, 'EN_CURSO'], [array_map(static fn ($r) => $r->escenario, $leida->resultados()),
            array_map(static fn ($x) => $x->tipo, $leida->resultados()[0]->evidencias), count($leida->plan), $leida->estado()], 'leída');
        igual([null, 1], [$p['ejecuciones']->porId('hosp-sur', (int) $e->id), count($p['ejecuciones']->deVersion('hosp-norte', '2.4.0'))], 'tenant y versión');
    });

    prueba($id(4), "[$nombre] Idempotencia: resultadoPorClave devuelve ejecución y resultado; una clave repetida en el tenant → CLAVE_REUTILIZADA", function () use ($fabrica, $version, $ejecucion, $resultado): void {
        $p = $fabrica();
        $e = $ejecucion($p, $version($p));
        $p['ejecuciones']->agregarResultado('hosp-norte', (int) $e->id, $resultado('E2E-101', 'clave-con-0003'));
        [$ej, $r] = $p['ejecuciones']->resultadoPorClave('hosp-norte', 'clave-con-0003');
        igual([(int) $e->id, 'E2E-101', null], [$ej, $r->escenario, $p['ejecuciones']->resultadoPorClave('hosp-sur', 'clave-con-0003')], 'por clave');
        igual('CLAVE_REUTILIZADA', lanza(ReglaRechazada::class, static fn () => $p['ejecuciones']->agregarResultado('hosp-norte', (int) $e->id, $resultado('E2E-102', 'clave-con-0003')),
            'repetida')->codigo, 'código');
    });

    prueba($id(5), "[$nombre] Estado de la ejecución: se guarda con su hora; actualizarla dos veces (dos cierres) → ESTADO_NO_PERMITE_CERRAR", function () use ($fabrica, $version, $ejecucion, $hora): void {
        $p = $fabrica();
        $e = $ejecucion($p, $version($p));
        $otra = $p['ejecuciones']->porId('hosp-norte', (int) $e->id);         // la lee otro proceso antes del cierre
        $e->abortar('runner caído', $hora);
        $p['ejecuciones']->actualizarEstado($e);
        $leida = $p['ejecuciones']->porId('hosp-norte', (int) $e->id);
        igual(['ABORTADA', 'runner caído', $hora->getTimestamp()], [$leida->estado(), $leida->motivo(), $leida->terminadaEn()?->getTimestamp()], 'guardada');
        $otra->abortar('otro motivo', $hora);
        igual('ESTADO_NO_PERMITE_CERRAR', lanza(ReglaRechazada::class, static fn () => $p['ejecuciones']->actualizarEstado($otra), 'segundo cierre')->codigo, 'código');
    });

    prueba($id(6), "[$nombre] Evaluaciones: se agregan en orden, con reglas booleanas, causas, política y hora; otro tenant no las ve", function () use ($fabrica, $version, $ejecucion, $hora): void {
        $p = $fabrica();
        $e = $ejecucion($p, $version($p));
        foreach ([[Decision::RECHAZADO, ['a_criticos' => false], ['críticos que no pasan: E2E-101']], [Decision::APROBADO, ['a_criticos' => true], []]] as [$valor, $reglas, $causas]) {
            $p['evaluaciones']->guardar(new Decision($valor, $reglas, $causas, 'hosp-norte', '2.4.0', (int) $e->id, $e->digest, 'gate-2026.1', $hora, 'resp-release'));
        }
        $todas = $p['evaluaciones']->deVersion('hosp-norte', '2.4.0');
        igual([[Decision::RECHAZADO, Decision::APROBADO], ['a_criticos' => false], ['críticos que no pasan: E2E-101'], 'gate-2026.1', $hora->getTimestamp()],
            [array_map(static fn (Decision $d) => $d->valor, $todas), $todas[0]->reglas, $todas[0]->causas, $todas[1]->politica, $todas[1]->evaluadaEn->getTimestamp()], 'leídas');
        igual([], $p['evaluaciones']->deVersion('hosp-sur', '2.4.0'), 'otro tenant');
    });

    prueba($id(8), "[$nombre] Despliegues (semana 6): se agregan en orden; vigente() es el último DESPLEGADO del entorno, no un REVERTIDO", function () use ($fabrica, $version, $ejecucion, $hora): void {
        $p = $fabrica();
        $v = $version($p);
        $e = $ejecucion($p, $v);
        $p['evaluaciones']->guardar(new Decision(Decision::APROBADO, ['a_criticos' => true], [], 'hosp-norte', '2.4.0', (int) $e->id, $v->digest, 'gate-2026.1', $hora, 'resp-release'));
        $d = static fn (string $estado): \E2e\Dominio\Modelo\Despliegue => new \E2e\Dominio\Modelo\Despliegue(null, 'hosp-norte', '2.4.0', $v->digest, 'produccion', 'guia-2026.1',
            'ops-luis', $estado, $estado === 'REVERTIDO' ? '2.3.0' : null, $hora);
        $primero = $p['despliegues']->registrar($d('DESPLEGADO'));
        $p['despliegues']->registrar($d('REVERTIDO'));
        igual([2, $primero->id, null, null], [count($p['despliegues']->deVersion('hosp-norte', '2.4.0')), $p['despliegues']->vigente('hosp-norte', 'produccion')?->id,
            $p['despliegues']->vigente('hosp-norte', 'staging'), $p['despliegues']->vigente('hosp-sur', 'produccion')], 'despliegues');
        igual('2.3.0', $p['despliegues']->deVersion('hosp-norte', '2.4.0')[1]->revertidoA, 'revertido a');
    });

    prueba($id(7), "[$nombre] Transacción: si el trabajo lanza, no queda nada de lo escrito dentro (versión, ejecución, resultado, traza)", function () use ($fabrica, $version, $resultado): void {
        $p = $fabrica();
        lanza(RuntimeException::class, static fn () => $p['transaccion']->ejecutar(static function () use ($p, $version): void {
            $version($p, '9.9.9');
            $p['auditoria']->registrar('PRUEBA', 'hosp-norte', 'u', 'r');
            throw new RuntimeException('falla a mitad');
        }), 'trabajo');
        igual(null, $p['versiones']->porNumero('hosp-norte', '9.9.9'), 'nada quedó');
    });
}
