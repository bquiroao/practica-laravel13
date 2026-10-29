<?php

declare(strict_types=1);

use Qa\Dominio\Evidencia\ReferenciaExterna;
use Qa\Dominio\Excepciones\ClaveEnUso;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\Campana;
use Qa\Dominio\Modelo\CasoPrueba;
use Qa\Dominio\Modelo\Defecto;
use Qa\Dominio\Modelo\EstadoDefecto;
use Qa\Dominio\Modelo\EvidenciaRegistrada;
use Qa\Dominio\Modelo\Intento;
use Qa\Dominio\Modelo\Resultado;
use Qa\Dominio\Modelo\Severidad;
use Qa\Persistencia\FabricaAdaptadores;
use Qa\Persistencia\Pdo\FabricaConexion;

/*
 * Patrón Repository (semana 4): la MISMA suite de contrato corre contra los dos adaptadores de cada puerto.
 * Si un adaptador cumple el contrato, los casos de uso no pueden notar cuál les tocó. Con QA_DB_DSN corre
 * también contra PostgreSQL; sin él, la variante «pdo» se omite.
 */
require_once dirname(__DIR__, 2) . '/bin/instalar-base.php';

/** @return array<string, callable(): array<string, object>> nombre del adaptador => fábrica de puertos recién instalados */
function adaptadoresDelContrato(): array
{
    $config = require dirname(__DIR__, 2) . '/config/config.php';
    $fabricas = ['memoria' => static fn (): array => FabricaAdaptadores::memoria($config, require dirname(__DIR__, 2) . '/database/datos-demo.php')];
    $fabricas['pdo'] = static function () use ($config): array {
        $dsn = getenv('QA_DB_DSN');
        if ($dsn === false || $dsn === '') {
            omitir('QA_DB_DSN no definido');
        }
        $pdo = FabricaConexion::crear($dsn, $config['db']['usuario'], $config['db']['clave']);
        instalarBase($pdo);

        return FabricaAdaptadores::crear(['persistencia' => 'pdo'] + $config, $pdo);
    };

    return $fabricas;
}

function intentoDeContrato(int $campana, string $caso, int $numero, string $clave, Resultado $r = Resultado::PASA, ?int $defecto = null): Intento
{
    $evidencias = $r === Resultado::PASA ? [] : [new EvidenciaRegistrada('captura', hash('sha256', $clave), 42, 'DATOS_CLINICOS', 'hosp-norte/' . hash('sha256', $clave))];

    return new Intento(null, $campana, $caso, $numero, $r, 'tito-tester', $evidencias, [new ReferenciaExterna('https://ci.hospital.gt/build/88', 'pipeline')],
        $defecto, null, null, $clave, str_repeat('a', 64), new \DateTimeImmutable('2026-10-28T15:00:00Z'));
}

$pasos = [['accion' => 'Abrir la nota firmada', 'esperado' => 'No se puede editar']];

foreach (adaptadoresDelContrato() as $nombre => $fabrica) {
    $id = static fn (int $n): string => sprintf('CON-%02d-%s', $n, $nombre === 'memoria' ? 'MEM' : 'PDO');

    prueba($id(1), "[$nombre] Casos: lo guardado vuelve igual (pasos, requisitos en orden), por tenant", function () use ($fabrica, $pasos): void {
        $p = $fabrica();
        $caso = CasoPrueba::disenar('hosp-norte', 'TC-300', 'ana-qa', 'Contrato', 'Pre', $pasos, 'MEDIA', ['RF-08', 'RF-07']);
        $p['casos']->guardar($caso);
        $leido = $p['casos']->porId('hosp-norte', 'TC-300');
        igual([$caso->titulo, $caso->pasos, ['RF-07', 'RF-08'], 'BORRADOR', null], [$leido->titulo, $leido->pasos, $leido->requisitos, $leido->estado, $leido->aprobador], 'caso');
        igual([null, []], [$p['casos']->porId('hosp-sur', 'TC-300'), array_keys($p['casos']->variosPorId('hosp-sur', ['TC-300']))], 'otro tenant');
        igual(['TC-108', 'TC-300'], array_map(static fn (CasoPrueba $c): string => $c->id, $p['casos']->queCubren('hosp-norte', 'RF-08')), 'RF-08: TC-108 (demo) y TC-300');
    });

    prueba($id(2), "[$nombre] Casos: guardar una versión que ya existe → CASO_EXISTENTE; aprobar dos veces → ESTADO_NO_PERMITE_APROBAR", function () use ($fabrica, $pasos): void {
        $p = $fabrica();
        $caso = CasoPrueba::disenar('hosp-norte', 'TC-300', 'ana-qa', 'Contrato', '', $pasos, 'MEDIA', ['RF-07']);
        $p['casos']->guardar($caso);
        igual('CASO_EXISTENTE', lanza(ReglaRechazada::class, static fn () => $p['casos']->guardar($caso), 'duplicado')->codigo, 'duplicado');
        $aprobado = $caso->aprobar('lider-qa');
        $p['casos']->aprobar($aprobado);
        igual('ESTADO_NO_PERMITE_APROBAR', lanza(ReglaRechazada::class, static fn () => $p['casos']->aprobar($aprobado), 'doble')->codigo, 'doble');
        igual(['APROBADO', 'lider-qa'], [$p['casos']->porId('hosp-norte', 'TC-300')->estado, $p['casos']->porId('hosp-norte', 'TC-300')->aprobador], 'aprobado');
    });

    prueba($id(3), "[$nombre] Matriz: críticos de varias funcionalidades, sin repetir, ordenados; lista vacía → []", function () use ($fabrica): void {
        $p = $fabrica();
        igual(['TC-106', 'TC-107', 'TC-108'], $p['casos']->criticosDe('hosp-norte', ['expediente', 'agenda-camas', 'expediente']), 'críticos');
        igual([[], []], [$p['casos']->criticosDe('hosp-norte', []), $p['casos']->criticosDe('hosp-sur', ['expediente'])], 'vacíos');
    });

    prueba($id(4), "[$nombre] Campañas: crear asigna id; otro tenant no la ve; el estado avanza solo desde el anterior; la decisión persiste", function () use ($fabrica): void {
        $p = $fabrica();
        $c = $p['campanas']->crear(Campana::planificar('hosp-norte', 'Contrato', ['expediente'], ['TC-107' => 1, 'TC-108' => 1], 0.9, 'lider-qa'));
        igual([1, null], [$c->id, $p['campanas']->porId('hosp-sur', 1)], 'id y tenant');
        $leida = $p['campanas']->porId('hosp-norte', 1);
        igual([['expediente'], ['TC-107' => 1, 'TC-108' => 1], 0.9, 'PLANIFICADA'], [$leida->funcionalidades, $leida->alcance, $leida->umbral, $leida->estado()->value], 'leída');
        $leida->iniciar('1.4.0', [], ['TC-107' => 'APROBADO', 'TC-108' => 'APROBADO']);
        $p['campanas']->actualizarEstado($leida);
        igual('ESTADO_CAMBIO_CONCURRENTE', lanza(ReglaRechazada::class, static fn () => $p['campanas']->actualizarEstado($leida), 'repetido')->codigo, 'no avanza dos veces');
        $p['campanas']->agregarIntento('hosp-norte', intentoDeContrato(1, 'TC-107', 1, 'contrato-0001'));
        $p['campanas']->agregarIntento('hosp-norte', intentoDeContrato(1, 'TC-108', 1, 'contrato-0002', Resultado::BLOQUEADO));
        $enCurso = $p['campanas']->porId('hosp-norte', 1);
        $enCurso->cerrar(1);
        $p['campanas']->actualizarEstado($enCurso);
        $cerrada = $p['campanas']->porId('hosp-norte', 1);
        igual(['CERRADA', '1.4.0', 'NO_GO', 0.5, 2], [$cerrada->estado()->value, $cerrada->build(), $cerrada->decision()->valor, $cerrada->decision()->tasa,
            count($cerrada->decision()->causas)], 'decisión');
    });

    prueba($id(5), "[$nombre] Intentos: vuelven en orden con evidencias y referencias; la clave es por tenant y única; el número no se repite", function () use ($fabrica): void {
        $p = $fabrica();
        $p['campanas']->crear(Campana::planificar('hosp-norte', 'Contrato', ['expediente'], ['TC-107' => 1], 0.95, 'lider-qa'));
        $a = $p['campanas']->agregarIntento('hosp-norte', intentoDeContrato(1, 'TC-107', 1, 'contrato-0001', Resultado::BLOQUEADO));
        $p['campanas']->agregarIntento('hosp-norte', intentoDeContrato(1, 'TC-107', 2, 'contrato-0002'));
        $leidos = $p['campanas']->porId('hosp-norte', 1)->intentos();
        igual([[1, 2], ['BLOQUEADO', 'PASA']], [array_map(static fn (Intento $i): int => $i->numero, $leidos), array_map(static fn (Intento $i): string => $i->resultado->value, $leidos)], 'orden');
        $e = $leidos[0]->evidencias[0];
        igual(['captura', hash('sha256', 'contrato-0001'), 42, 'DATOS_CLINICOS'], [$e->tipo, $e->huella, $e->tamano, $e->clasificacion], 'evidencia');
        igual(['https://ci.hospital.gt/build/88', (new \DateTimeImmutable('2026-10-28T15:00:00Z'))->getTimestamp()], [$leidos[0]->referencias[0]->url, $leidos[0]->registradoEn->getTimestamp()], 'referencia y fecha');
        igual([$a->id, null], [$p['campanas']->intentoPorClave('hosp-norte', 'contrato-0001')?->id, $p['campanas']->intentoPorClave('hosp-sur', 'contrato-0001')], 'clave por tenant');
        lanza(ClaveEnUso::class, static fn () => $p['campanas']->agregarIntento('hosp-norte', intentoDeContrato(1, 'TC-107', 3, 'contrato-0001')), 'clave repetida');
        igual('INTENTO_CONCURRENTE', lanza(ReglaRechazada::class, static fn () => $p['campanas']->agregarIntento('hosp-norte', intentoDeContrato(1, 'TC-107', 2, 'contrato-0003')), 'número')->codigo, 'número repetido');
    });

    prueba($id(6), "[$nombre] Defectos: estado, severidad y prioridad persisten; otro tenant no lo ve; críticos abiertos por caso", function () use ($fabrica): void {
        $p = $fabrica();
        $p['campanas']->crear(Campana::planificar('hosp-norte', 'Contrato', ['expediente'], ['TC-107' => 1, 'TC-108' => 1], 0.95, 'lider-qa'));
        $d = $p['defectos']->crear(Defecto::reportar('hosp-norte', 1, 'TC-107', 'Editable', ['Abrir', 'Editar'], Severidad::MAYOR, true, 'tito-tester'));
        $p['defectos']->crear(Defecto::reportar('hosp-norte', 1, 'TC-108', 'Otro', ['Abrir'], Severidad::CRITICA, true, 'tito-tester'));   // sin clasificar: no cuenta
        igual(0, $p['defectos']->criticosAbiertos('hosp-norte', ['TC-107', 'TC-108']), 'NUEVO no cuenta como crítico');
        $d->clasificar(Severidad::CRITICA, 'ALTA', 'lider-qa', 'Altera un registro firmado');
        $p['defectos']->actualizar($d);
        $leido = $p['defectos']->porId('hosp-norte', (int) $d->id);
        igual([EstadoDefecto::ABIERTO, Severidad::CRITICA, 'ALTA', Severidad::MAYOR, ['Abrir', 'Editar']], [$leido->estado(), $leido->severidad(), $leido->prioridad(),
            $leido->propuesta, $leido->pasos], 'leído');
        igual([1, 0, 0, null], [$p['defectos']->criticosAbiertos('hosp-norte', ['TC-107', 'TC-108']), $p['defectos']->criticosAbiertos('hosp-norte', ['TC-108']),
            $p['defectos']->criticosAbiertos('hosp-norte', []), $p['defectos']->porId('hosp-sur', (int) $d->id)], 'críticos y tenant');
        igual(2, count($p['defectos']->deCampana('hosp-norte', 1)), 'de la campaña');
    });

    prueba($id(7), "[$nombre] Transacción: si el trabajo lanza, no queda nada de lo escrito dentro (campaña, defecto, traza)", function () use ($fabrica): void {
        $p = $fabrica();
        lanza(\RuntimeException::class, static fn () => $p['transaccion']->ejecutar(static function () use ($p): void {
            $p['campanas']->crear(Campana::planificar('hosp-norte', 'Contrato', ['expediente'], ['TC-107' => 1], 0.95, 'lider-qa'));
            $p['defectos']->crear(Defecto::reportar('hosp-norte', 1, 'TC-107', 'X', ['a'], Severidad::MENOR, true, 'tito-tester'));
            $p['auditoria']->registrar('PRUEBA', 'hosp-norte', 'u', 'r');
            throw new \RuntimeException('falla a mitad');
        }), 'trabajo');
        igual([null, null], [$p['campanas']->porId('hosp-norte', 1), $p['defectos']->porId('hosp-norte', 1)], 'nada quedó');
    });
}
