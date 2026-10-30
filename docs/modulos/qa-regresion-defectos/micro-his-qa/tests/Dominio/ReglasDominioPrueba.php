<?php

declare(strict_types=1);

use Qa\Dominio\Evidencia\Clasificacion;
use Qa\Dominio\Evidencia\EvidenciaInvalida;
use Qa\Dominio\Evidencia\FabricaEvidencias;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\Campana;
use Qa\Dominio\Modelo\CasoPrueba;
use Qa\Dominio\Modelo\Decision;
use Qa\Dominio\Modelo\Defecto;
use Qa\Dominio\Modelo\EstadoDefecto;
use Qa\Dominio\Modelo\Intento;
use Qa\Dominio\Modelo\Resultado;
use Qa\Dominio\Modelo\Severidad;

$pasos = [['accion' => 'Abrir la nota firmada', 'esperado' => 'No se puede editar']];
$intento = static fn (int $campana, string $caso, int $n, Resultado $r): Intento => new Intento(null, $campana, $caso, $n, $r, 'tito-tester', [], [],
    null, null, $r === Resultado::NO_APLICA ? 'No aplica a este build' : null, "c-$caso-$n", str_repeat('0', 64), new \DateTimeImmutable('2026-10-28'));
$enCurso = static function (array $casos, float $umbral = 0.95): Campana {
    $c = Campana::planificar('hosp-norte', 'Regresión', ['expediente'], array_fill_keys($casos, 1), $umbral, 'lider-qa')->conId(1);
    $c->iniciar('1.4.0', [], array_fill_keys($casos, CasoPrueba::APROBADO));

    return $c;
};

prueba('DOM-01', 'R-01: un caso sin pasos, sin resultado esperado, con prioridad o requisito inválido se rechaza con TODOS los campos', function () use ($pasos): void {
    $e = lanza(ReglaRechazada::class, static fn () => CasoPrueba::disenar('hosp-norte', 'X-1', 'ana-qa', ' ', '', [['accion' => 'a', 'esperado' => '']],
        'URGENTE', ['RF-1', 'RF-07']), 'caso inválido');
    igual(['DATOS_INVALIDOS', ['id', 'titulo', 'pasos[0].esperado', 'prioridad', 'requisitos']], [$e->codigo, $e->detalle], 'campos');
    $ok = CasoPrueba::disenar('hosp-norte', 'TC-200', 'ana-qa', '  Nota firmada  ', '', $pasos, 'ALTA', ['RF-07', 'RF-07']);
    igual([1, 'BORRADOR', 'Nota firmada', ['RF-07']], [$ok->version, $ok->estado, $ok->titulo, $ok->requisitos], 'caso válido');
});

prueba('DOM-02', 'R-02: el autor no aprueba su propio caso; un aprobado no se vuelve a aprobar; aprobar devuelve OTRA instancia', function () use ($pasos): void {
    $caso = CasoPrueba::disenar('hosp-norte', 'TC-200', 'ana-qa', 'Nota', '', $pasos, 'ALTA', ['RF-07']);
    igual('APROBACION_POR_AUTOR', lanza(ReglaRechazada::class, static fn () => $caso->aprobar('ana-qa'), 'autor')->codigo, 'autor');
    $aprobado = $caso->aprobar('lider-qa');
    igual(['BORRADOR', 'APROBADO', 'lider-qa'], [$caso->estado, $aprobado->estado, $aprobado->aprobador], 'inmutable');
    igual('ESTADO_NO_PERMITE_APROBAR', lanza(ReglaRechazada::class, static fn () => $aprobado->aprobar('otro'), 'doble')->codigo, 'doble aprobación');
});

prueba('DOM-03', 'R-03: no inicia si falta un crítico de la matriz (los lista), sin build válido o con un caso no aprobado', function (): void {
    $c = Campana::planificar('hosp-norte', 'R', ['expediente'], ['TC-109' => 1, 'TC-111' => 1], 0.95, 'lider-qa');
    $faltan = lanza(ReglaRechazada::class, static fn () => $c->iniciar('1.4.0', ['TC-108', 'TC-107'], []), 'críticos');
    igual(['ALCANCE_INCOMPLETO', ['TC-107', 'TC-108']], [$faltan->codigo, $faltan->detalle], 'faltan críticos, ordenados');
    igual(['build'], lanza(ReglaRechazada::class, static fn () => $c->iniciar('ultimo', [], []), 'build')->detalle, 'build');
    $noAprobado = lanza(ReglaRechazada::class, static fn () => $c->iniciar('1.4.0', ['TC-109'], ['TC-109' => 'APROBADO', 'TC-111' => 'BORRADOR']), 'borrador');
    igual(['CASO_NO_APROBADO', ['TC-111']], [$noAprobado->codigo, $noAprobado->detalle], 'no aprobado');
    igual('PLANIFICADA', $c->estado()->value, 'sigue planificada');
});

prueba('DOM-04', 'R-04: solo una campaña EN_CURSO admite resultados, y solo de su alcance', function () use ($enCurso): void {
    $planificada = Campana::planificar('hosp-norte', 'R', ['expediente'], ['TC-107' => 1], 0.95, 'lider-qa');
    $c = $enCurso(['TC-107']);
    igual([false, true, false], [$planificada->admite('TC-107'), $c->admite('TC-107'), $c->admite('TC-101')], 'admite');
});

prueba('DOM-05', 'R-09: GO con 0 críticos y tasa ≥ umbral; cuenta el ÚLTIMO intento de cada caso y excluye NO_APLICA', function () use ($enCurso, $intento): void {
    $c = $enCurso(['TC-107', 'TC-108', 'TC-109']);
    $c->agregar($intento(1, 'TC-107', 1, Resultado::FALLA));
    $c->agregar($intento(1, 'TC-107', 2, Resultado::PASA));       // la re-ejecución reemplaza a la falla
    $c->agregar($intento(1, 'TC-108', 1, Resultado::PASA));
    $c->agregar($intento(1, 'TC-109', 1, Resultado::NO_APLICA));  // no cuenta ni a favor ni en contra
    $d = $c->cerrar(0);
    igual([Decision::GO, 1.0, []], [$d->valor, $d->tasa, $d->causas], 'decisión');
});

prueba('DOM-06', 'R-09: NO_GO por crítico abierto y por tasa bajo el umbral (con las dos causas); no cierra con casos pendientes', function () use ($enCurso, $intento): void {
    $p = $enCurso(['TC-107', 'TC-108']);
    $p->agregar($intento(1, 'TC-107', 1, Resultado::PASA));
    igual(['CASOS_PENDIENTES', ['TC-108']], (static function () use ($p): array {
        $e = lanza(ReglaRechazada::class, static fn () => $p->cerrar(0), 'pendientes');

        return [$e->codigo, $e->detalle];
    })(), 'pendientes');
    $p->agregar($intento(1, 'TC-108', 1, Resultado::FALLA));
    $d = $p->cerrar(1);
    igual([Decision::NO_GO, 0.5, 2], [$d->valor, $d->tasa, count($d->causas)], 'NO_GO');
    igual('ESTADO_NO_PERMITE_CERRAR', lanza(ReglaRechazada::class, static fn () => $p->cerrar(0), 'doble cierre')->codigo, 'cerrada');
});

prueba('DOM-07', 'R-07: CRÍTICA exige pasos y evidencia; cambiar la severidad propuesta exige motivo', function (): void {
    $sinEvidencia = Defecto::reportar('hosp-norte', 1, 'TC-107', 'Se puede editar la nota', ['Abrir', 'Editar'], Severidad::MAYOR, false, 'tito-tester');
    igual('DEFECTO_INCOMPLETO', lanza(ReglaRechazada::class, static fn () => $sinEvidencia->clasificar(Severidad::CRITICA, 'ALTA', 'lider-qa', 'grave'), 'crítica')->codigo, 'crítica sin evidencia');
    $d = Defecto::reportar('hosp-norte', 1, 'TC-107', 'Se puede editar la nota', ['Abrir', 'Editar'], Severidad::MAYOR, true, 'tito-tester');
    igual('MOTIVO_REQUERIDO', lanza(ReglaRechazada::class, static fn () => $d->clasificar(Severidad::CRITICA, 'ALTA', 'lider-qa', '  '), 'motivo')->codigo, 'motivo');
    $d->clasificar(Severidad::CRITICA, 'ALTA', 'lider-qa', 'Altera un registro clínico firmado');
    igual([EstadoDefecto::ABIERTO, Severidad::CRITICA, true], [$d->estado(), $d->severidad(), $d->criticoAbierto()], 'clasificado');
});

prueba('DOM-08', 'R-08: el desarrollador solo marca CORREGIDO; PASA en la re-ejecución cierra y FALLA reabre', function (): void {
    $d = Defecto::reportar('hosp-norte', 1, 'TC-107', 'Editable', ['Abrir'], Severidad::MAYOR, true, 'tito-tester');
    igual('ESTADO_NO_PERMITE_CORREGIR', lanza(ReglaRechazada::class, static fn () => $d->marcarCorregido('1.4.1', 'dev-luis'), 'nuevo')->codigo, 'sin clasificar');
    $d->clasificar(Severidad::MAYOR, 'ALTA', 'lider-qa', null);
    $d->marcarCorregido('1.4.1', 'dev-luis');
    $d->verificar(Resultado::FALLA, 'tito-tester');
    igual(EstadoDefecto::REABIERTO, $d->estado(), 'reabierto');
    $d->marcarCorregido('1.4.2', 'dev-luis');
    igual('RESULTADO_NO_VERIFICA', lanza(ReglaRechazada::class, static fn () => $d->verificar(Resultado::BLOQUEADO, 'tito-tester'), 'bloqueado')->codigo, 'bloqueado no verifica');
    $d->verificar(Resultado::PASA, 'tito-tester');
    igual([EstadoDefecto::CERRADO, 5], [$d->estado(), count($d->historialNuevo())], 'cerrado con su historial');
});

prueba('DOM-09', 'Evidencias (semana 2): la fábrica valida el formato por la firma; la huella es sha256 del contenido', function (): void {
    $c = FabricaEvidencias::crear('captura', Escenario::PNG, Clasificacion::DATOS_CLINICOS);
    igual(['captura', hash('sha256', Escenario::PNG), Clasificacion::DATOS_CLINICOS], [$c->tipo(), $c->huella(), $c->clasificacion()], 'captura');
    igual('TIPO_NO_PERMITIDO', lanza(EvidenciaInvalida::class, static fn () => FabricaEvidencias::crear('captura', 'no es imagen', Clasificacion::GENERAL), 'firma')->codigo, 'firma');
    igual('TIPO_NO_PERMITIDO', lanza(EvidenciaInvalida::class, static fn () => FabricaEvidencias::crear('video', 'x', Clasificacion::GENERAL), 'tipo')->codigo, 'tipo');
});
