<?php

declare(strict_types=1);

use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Dominio\Modelo\Decision;
use E2e\Dominio\Modelo\Ejecucion;
use E2e\Dominio\Modelo\EscenarioE2E;
use E2e\Dominio\Modelo\Evidencia;
use E2e\Dominio\Modelo\PoliticaGate;
use E2e\Dominio\Modelo\QualityGate;
use E2e\Dominio\Modelo\Resultado;
use E2e\Dominio\Modelo\ResultadoEscenario;
use E2e\Dominio\Modelo\Version;

$catalogo = static fn (): array => [
    new EscenarioE2E('t', 'E2E-101', 'Admitir', 'admision', true, ['RF-01'], 'SINT-A', 'x'),
    new EscenarioE2E('t', 'E2E-102', 'Prescribir', 'prescripciones', true, ['RF-05'], 'SINT-B', 'x'),
    new EscenarioE2E('t', 'E2E-103', 'Laboratorio', 'laboratorio', false, ['RF-07'], 'SINT-C', 'x'),
    new EscenarioE2E('t', 'E2E-104', 'Epicrisis', 'expediente', false, ['RF-08'], 'SINT-D', 'x'),
];
$version = static fn (array $modulos = ['expediente']): Version => Version::registrar('t', '2.4.0', 'c56f169', 'sha256:' . str_repeat('a', 64), $modulos, 'ci');
$hora = new DateTimeImmutable('2026-10-29T14:00:00Z');
$res = static fn (string $esc, bool $crit, string $r, int $int = 1, array $ev = []): ResultadoEscenario
    => ResultadoEscenario::desde($esc, $crit, $r, $int, $ev, 'k-' . $esc, 'h', $hora);

prueba('DOM-01', 'R-01: un escenario E2E sin un conjunto de datos SINT-… se rechaza (DATOS_NO_SINTETICOS); los campos inválidos se listan todos', function (): void {
    $e = lanza(ReglaRechazada::class, static fn () => EscenarioE2E::registrar('t', 'E2E-200', 'Ok', 'admision', true, ['RF-01'], 'PACIENTE-REAL-77', 'x'), 'datos');
    igual('DATOS_NO_SINTETICOS', $e->codigo, 'código');
    $e = lanza(ReglaRechazada::class, static fn () => EscenarioE2E::registrar('t', 'X1', '', 'Admision', true, ['RF-1'], 'SINT-A', 'x'), 'campos');
    igual(['DATOS_INVALIDOS', ['id', 'titulo', 'modulo', 'requisitos']], [$e->codigo, $e->detalle], 'campos');
});

prueba('DOM-02', 'R-02: el plan debe incluir todos los críticos y los escenarios de los módulos afectados; lista los que faltan', function () use ($catalogo, $version): void {
    $v = $version();
    $e = lanza(ReglaRechazada::class, static fn () => $v->planificar($catalogo(), ['E2E-101', 'E2E-103']), 'plan');
    igual(['PLAN_INCOMPLETO', ['E2E-102', 'E2E-104']], [$e->codigo, $e->detalle], 'faltan el crítico 102 y el 104 (módulo expediente afectado)');
    $v->planificar($catalogo(), ['E2E-101', 'E2E-102', 'E2E-104']);
    igual(['E2E-101' => true, 'E2E-102' => true, 'E2E-104' => false], $v->plan(), 'plan con su criticidad');
});

prueba('DOM-03', 'R-02: el plan se congela al iniciar la primera ejecución y ya no cambia (nadie quita el escenario que falla)', function () use ($catalogo, $version, $hora): void {
    $v = $version();
    $v->planificar($catalogo(), ['E2E-101', 'E2E-102', 'E2E-104']);
    Ejecucion::iniciar($v, $v->digest, 'staging', $hora);
    igual('PLAN_CONGELADO', lanza(ReglaRechazada::class, static fn () => $v->planificar($catalogo(), ['E2E-101', 'E2E-102', 'E2E-104', 'E2E-103']), 'replanificar')->codigo, 'código');
});

prueba('DOM-04', 'R-03: una ejecución sobre otro digest no se inicia (DIGEST_DISTINTO); sin plan tampoco', function () use ($catalogo, $version, $hora): void {
    $v = $version();
    igual('VERSION_SIN_PLAN', lanza(ReglaRechazada::class, static fn () => Ejecucion::iniciar($v, $v->digest, 'staging', $hora), 'sin plan')->codigo, 'sin plan');
    $v->planificar($catalogo(), ['E2E-101', 'E2E-102', 'E2E-104']);
    igual('DIGEST_DISTINTO', lanza(ReglaRechazada::class, static fn () => Ejecucion::iniciar($v, 'sha256:' . str_repeat('b', 64), 'staging', $hora), 'digest')->codigo, 'digest');
});

prueba('DOM-05', 'R-06: el pipeline reporta PASA con 2 intentos y el dominio lo registra INESTABLE; INESTABLE no se acepta como dato de entrada', function () use ($res): void {
    igual([Resultado::PASA, Resultado::INESTABLE, Resultado::FALLA], [$res('E2E-101', true, 'PASA')->resultado, $res('E2E-101', true, 'PASA', 2)->resultado,
        $res('E2E-101', true, 'FALLA', 2)->resultado], 'resultados');
    igual(['DATOS_INVALIDOS', ['resultado']], [($e = lanza(ReglaRechazada::class, static fn () => $res('E2E-101', true, 'INESTABLE', 2), 'inestable'))->codigo, $e->detalle], 'entrada');
});

prueba('DOM-06', 'R-04: solo EN_CURSO admite resultados, solo del plan y uno por escenario; completar exige todos', function () use ($catalogo, $version, $hora, $res): void {
    $v = $version();
    $v->planificar($catalogo(), ['E2E-101', 'E2E-102', 'E2E-104']);
    $e = Ejecucion::iniciar($v, $v->digest, 'staging', $hora);
    igual('ESCENARIO_FUERA_DEL_PLAN', lanza(ReglaRechazada::class, static fn () => $e->critico('E2E-103'), 'fuera')->codigo, 'fuera del plan');
    $e->agregar($res('E2E-101', true, 'PASA'));
    igual('RESULTADO_YA_REPORTADO', lanza(ReglaRechazada::class, static fn () => $e->agregar($res('E2E-101', true, 'FALLA')), 'duplicado')->codigo, 'duplicado');
    igual(['ESCENARIOS_SIN_RESULTADO', ['E2E-102', 'E2E-104']], [($x = lanza(ReglaRechazada::class, static fn () => $e->completar($hora), 'completar'))->codigo, $x->detalle], 'faltan');
    $e->abortar('runner sin memoria', $hora);
    igual('EJECUCION_NO_ADMITE_RESULTADOS', lanza(ReglaRechazada::class, static fn () => $e->agregar($res('E2E-102', true, 'PASA')), 'abortada')->codigo, 'abortada');
});

prueba('DOM-07', 'Evidencia: tipo permitido, no vacía y de 20 MB o menos', function (): void {
    igual(['TIPO_NO_PERMITIDO', 'EVIDENCIA_VACIA', 'EVIDENCIA_DEMASIADO_GRANDE'], [
        lanza(ReglaRechazada::class, static fn () => Evidencia::validar('pdf', 'x'), 'tipo')->codigo,
        lanza(ReglaRechazada::class, static fn () => Evidencia::validar('traza', ''), 'vacía')->codigo,
        lanza(ReglaRechazada::class, static fn () => Evidencia::validar('video', str_repeat('x', Evidencia::TAMANO_MAXIMO + 1)), 'grande')->codigo,
    ], 'códigos');
    Evidencia::validar('video', str_repeat('x', Evidencia::TAMANO_MAXIMO));
});

prueba('DOM-08', 'R-09: gate APROBADO solo si se cumplen digest y a–e; cada regla incumplida da su causa', function () use ($catalogo, $version, $hora, $res): void {
    $politica = new PoliticaGate('gate-test', 0.5, 0, 24);
    $evaluar = static function (array $resultados, string $horaEval = '2026-10-29T20:00:00Z', array $confirmadas = ['ok']) use ($catalogo, $version, $hora, $politica): Decision {
        $v = $version();
        $v->planificar($catalogo(), ['E2E-101', 'E2E-102', 'E2E-103', 'E2E-104']);
        $e = Ejecucion::iniciar($v, $v->digest, 'staging', $hora)->conId(1);
        foreach ($resultados as $r) {
            $e->agregar($r);
        }
        $e->completar($hora);

        return QualityGate::evaluar($politica, $e, $v, new DateTimeImmutable($horaEval), static fn (string $s): bool => in_array($s, $confirmadas, true), 'rel');
    };
    $ev = [new Evidencia('traza', 'ok', 10, 'm')];
    $sano = [$res('E2E-101', true, 'PASA'), $res('E2E-102', true, 'PASA'), $res('E2E-103', false, 'PASA'), $res('E2E-104', false, 'FALLA', 1, $ev)];
    igual([Decision::APROBADO, []], [($d = $evaluar($sano))->valor, $d->causas], 'sano: 1 de 2 no críticos = 50 % y la falla con evidencia');
    $d = $evaluar([$res('E2E-101', true, 'PASA', 2, $ev), $res('E2E-102', true, 'PASA'), $res('E2E-103', false, 'OMITIDO', 0), $res('E2E-104', false, 'FALLA')],
        '2026-10-30T15:00:00Z');
    igual(['digest' => true, 'a_criticos' => false, 'b_tasa' => false, 'c_inestables' => false, 'd_evidencia' => false, 'e_antiguedad' => false], $d->reglas, 'todas fallan');
    igual(5, count($d->causas), 'una causa por regla');
});

prueba('DOM-09', 'R-09: no se evalúa una ejecución abortada o en curso (EJECUCION_NO_COMPLETADA)', function () use ($catalogo, $version, $hora): void {
    $v = $version();
    $v->planificar($catalogo(), ['E2E-101', 'E2E-102', 'E2E-104']);
    $e = Ejecucion::iniciar($v, $v->digest, 'staging', $hora);
    $gate = static fn () => QualityGate::evaluar(new PoliticaGate('p', 1.0, 0, 24), $e, $v, $hora, static fn (): bool => true, 'rel');
    igual('EJECUCION_NO_COMPLETADA', lanza(ReglaRechazada::class, $gate, 'en curso')->codigo, 'en curso');
    $e->abortar('tiempo vencido', $hora);
    igual('EJECUCION_NO_COMPLETADA', lanza(ReglaRechazada::class, $gate, 'abortada')->codigo, 'abortada');
});

prueba('DOM-10', 'R-03 en el gate (defensa en profundidad): una ejecución cuyo digest no es el de la versión (datos importados o alterados) se RECHAZA', function () use ($catalogo, $version, $hora, $res): void {
    $v = $version();
    $v->planificar($catalogo(), ['E2E-101', 'E2E-102', 'E2E-104']);
    $otro = new Ejecucion(1, 't', '2.4.0', 'sha256:' . str_repeat('b', 64), 'staging', $v->plan(), $hora);
    foreach (['E2E-101' => true, 'E2E-102' => true, 'E2E-104' => false] as $esc => $crit) {
        $otro->agregar($res($esc, $crit, 'PASA'));
    }
    $otro->completar($hora);
    $d = QualityGate::evaluar(new PoliticaGate('p', 0.0, 0, 24), $otro, $v, $hora, static fn (): bool => true, 'rel');
    igual([Decision::RECHAZADO, false, ['la ejecución probó otro digest']], [$d->valor, $d->reglas['digest'], $d->causas], 'decisión');
});
