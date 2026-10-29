<?php

declare(strict_types=1);

/*
 * Semana 6 — Cambio práctico R-15: «un defecto MAYOR abierto en un caso CRÍTICO de la matriz también bloquea el GO».
 * Estas pruebas se escribieron ANTES del código (evidencia/cambio-1-antes-rojo.txt).
 */

use Qa\Dominio\Modelo\Campana;
use Qa\Dominio\Modelo\CasoPrueba;
use Qa\Dominio\Modelo\Decision;
use Qa\Dominio\Modelo\Defecto;
use Qa\Dominio\Modelo\EstadoDefecto;
use Qa\Dominio\Modelo\Intento;
use Qa\Dominio\Modelo\Resultado;
use Qa\Dominio\Modelo\Severidad;
use Qa\Persistencia\FabricaAdaptadores;

prueba('CAM-01', 'Dominio: GO con 0 críticos y tasa 100 %, pero 1 MAYOR abierto en un caso crítico → NO_GO con su propia causa', function (): void {
    $c = Campana::planificar('hosp-norte', 'R', ['expediente'], ['TC-107' => 1], 0.95, 'lider-qa')->conId(1);
    $c->iniciar('1.4.0', [], ['TC-107' => CasoPrueba::APROBADO]);
    $c->agregar(new Intento(null, 1, 'TC-107', 1, Resultado::PASA, 't', [], [], null, null, null, 'k-cam-01', str_repeat('0', 64), new \DateTimeImmutable()));
    $d = $c->cerrar(0, 1);
    igual([Decision::NO_GO, 1.0, ['1 defecto MAYOR abierto en un caso crítico de la matriz']], [$d->valor, $d->tasa, $d->causas], 'decisión');
});

prueba('CAM-02', 'Caso de uso: MAYOR abierto en TC-107 (crítico en «expediente») → NO_GO; MAYOR abierto en TC-109 (no crítico) → GO', function (): void {
    foreach (['TC-107' => 'NO_GO', 'TC-109' => 'GO'] as $caso => $esperado) {
        $e = new Escenario();
        $id = $e->campanaEnCurso(['TC-107', 'TC-108', 'TC-109']);
        $e->sembrarDefecto($id, $caso, EstadoDefecto::ABIERTO);   // MAYOR, clasificado, abierto
        foreach (['TC-107', 'TC-108', 'TC-109'] as $c) {
            $e->registrar($id, $c, 'PASA');
        }
        $r = $e->uc['cerrar']->ejecutar('lider-qa', 'hosp-norte', $id);
        igual($esperado, $r->datos['decision'], "MAYOR en $caso");
    }
});

prueba('CAM-03', 'Un MAYOR CORREGIDO todavía bloquea (no está cerrado); uno CERRADO por la re-ejecución, no', function (): void {
    foreach ([EstadoDefecto::CORREGIDO->value => 'NO_GO', EstadoDefecto::CERRADO->value => 'GO'] as $estado => $esperado) {
        $e = new Escenario();
        $id = $e->campanaEnCurso(['TC-107', 'TC-108']);
        $e->sembrarDefecto($id, 'TC-107', EstadoDefecto::from($estado));
        $e->registrar($id, 'TC-107', 'PASA');
        $e->registrar($id, 'TC-108', 'PASA');
        igual($esperado, $e->uc['cerrar']->ejecutar('lider-qa', 'hosp-norte', $id)->datos['decision'], "MAYOR $estado");
    }
});

prueba('CAM-04', 'Trazabilidad: cada defecto dice si bloquea el GO (CRÍTICA abierto, o MAYOR abierto en caso crítico)', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso(['TC-107', 'TC-108', 'TC-109']);
    $e->sembrarDefecto($id, 'TC-107', EstadoDefecto::ABIERTO);
    $e->sembrarDefecto($id, 'TC-109', EstadoDefecto::ABIERTO);
    $t = $e->uc['trazabilidad']->ejecutar('producto', 'hosp-norte', $id)->datos['casos'];
    igual([true, false], [$t[0]['defectos'][0]['bloquea_go'], $t[2]['defectos'][0]['bloquea_go']], 'bloquea_go');
});

prueba('CAM-05', 'Repository: mayoresAbiertos cuenta los MAYOR no cerrados (CORREGIDO incluido) de los casos pedidos, en memoria y en PostgreSQL', function (): void {
    $fabricas = adaptadoresDelContrato();
    foreach ($fabricas as $nombre => $fabrica) {
        if ($nombre === 'pdo' && !getenv('QA_DB_DSN')) {
            continue;
        }
        $p = $fabrica();
        $p['campanas']->crear(Campana::planificar('hosp-norte', 'C', ['expediente'], ['TC-107' => 1, 'TC-109' => 1], 0.95, 'lider-qa'));
        foreach ([['TC-107', Severidad::MAYOR, EstadoDefecto::ABIERTO], ['TC-107', Severidad::MAYOR, EstadoDefecto::CERRADO],
            ['TC-109', Severidad::MAYOR, EstadoDefecto::REABIERTO], ['TC-107', Severidad::MENOR, EstadoDefecto::ABIERTO],
            ['TC-107', Severidad::MAYOR, EstadoDefecto::CORREGIDO]] as [$caso, $sev, $estado]) {   // CORREGIDO aún no está verificado: cuenta
            $p['defectos']->crear(new Defecto(null, 'hosp-norte', 1, $caso, 'X', ['a'], $sev, true, 't', $estado, $sev, 'ALTA'));
        }
        igual([2, 3, 0, 0], [$p['defectos']->mayoresAbiertos('hosp-norte', ['TC-107']), $p['defectos']->mayoresAbiertos('hosp-norte', ['TC-107', 'TC-109']),
            $p['defectos']->mayoresAbiertos('hosp-norte', []), $p['defectos']->mayoresAbiertos('hosp-sur', ['TC-107'])], $nombre);
    }
});
