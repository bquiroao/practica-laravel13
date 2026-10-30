<?php
/** AcusarAlerta, EscalarAlertasVencidas y CerrarAlerta con dobles y reloj fijo. @var Pruebas $t */
declare(strict_types=1);

require_once __DIR__ . '/../dobles/escenario.php';

use AlertasCriticas\Domain\{AlertaNoEncontradaException, EstadoAlerta};

$t->prueba('CA-10/CA-11 escalar con reloj fijo: 10:14 nada, 10:15 sube a nivel 1', function () use ($t) {
    $e = escenario();
    $a = $e['registrar']->ejecutar(datosAlerta())['alerta'];
    $e['reloj']->avanzarMinutos(14);
    $t->igual(['escaladas' => 0, 'agotadas' => 0], $e['escalar']->ejecutar());
    $e['reloj']->avanzarMinutos(1);
    $t->igual(['escaladas' => 1, 'agotadas' => 0], $e['escalar']->ejecutar());
    $t->igual([1, 'jefe.servicio01'], [$a->nivel(), end($e['notificador']->enviados)['usuario']]);
});

$t->prueba('CA-12 una alerta acusada no se escala aunque pase el plazo', function () use ($t) {
    $e = escenario();
    $a = $e['registrar']->ejecutar(datosAlerta())['alerta'];
    $e['acusar']->ejecutar($a->id, 'enf.turno01', 'HOSP-GUA-01');
    $e['reloj']->avanzarMinutos(30);
    $t->igual(['escaladas' => 0, 'agotadas' => 0], $e['escalar']->ejecutar());
});

$t->prueba('CA-13 tras nivel 2 se agota una sola vez', function () use ($t) {
    $e = escenario();
    $a = $e['registrar']->ejecutar(datosAlerta())['alerta'];
    $conteos = [];
    foreach ([15, 15, 15, 15] as $minutos) {
        $e['reloj']->avanzarMinutos($minutos);
        $conteos[] = $e['escalar']->ejecutar();
    }
    $t->igual([[1, 0], [1, 0], [0, 1], [0, 0]], array_map(fn ($c) => [$c['escaladas'], $c['agotadas']], $conteos));
    $t->igual([2, true], [$a->nivel(), $a->escalamientoAgotado()]);
});

$t->prueba('CA-08 acusar dos veces devuelve cambio=false y guarda una sola vez', function () use ($t) {
    $e = escenario();
    $a = $e['registrar']->ejecutar(datosAlerta())['alerta'];
    $antes = $e['repo']->guardados;
    $r1 = $e['acusar']->ejecutar($a->id, 'enf.turno01', 'HOSP-GUA-01');
    $r2 = $e['acusar']->ejecutar($a->id, 'enf.turno01', 'HOSP-GUA-01');
    $t->igual([true, false, $antes + 1], [$r1['cambio'], $r2['cambio'], $e['repo']->guardados]);
});

$t->prueba('CA-09 RNF-01 un usuario de otro tenant no encuentra la alerta', function () use ($t) {
    $e = escenario();
    $a = $e['registrar']->ejecutar(datosAlerta())['alerta'];
    $t->lanza(AlertaNoEncontradaException::class, fn () => $e['acusar']->ejecutar($a->id, 'enf.turno21', 'HOSP-XEL-02'));
    $t->igual(EstadoAlerta::ENVIADA, $a->estado());
});

$t->prueba('CA-15 flujo completo: creada, enviada, acusada y cerrada', function () use ($t) {
    $e = escenario();
    $a = $e['registrar']->ejecutar(datosAlerta())['alerta'];
    $e['reloj']->avanzarMinutos(5);
    $e['acusar']->ejecutar($a->id, 'med.tratante01', 'HOSP-GUA-01');
    $e['cerrar']->ejecutar($a->id, 'med.tratante01', 'Atendida (nota ficticia)', 'HOSP-GUA-01');
    $t->igual(['creada', 'enviada', 'acusada', 'cerrada'], eventos($a));
    $t->igual('10:05', $a->historial()[2]->momento->format('H:i'));
});
