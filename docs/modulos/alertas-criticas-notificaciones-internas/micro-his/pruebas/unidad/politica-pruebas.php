<?php
/** Plazos de acuse y vencimiento (PoliticaEscalamiento). @var Pruebas $t */
declare(strict_types=1);

use AlertasCriticas\Domain\{Alerta, EstadoAlerta, PoliticaEscalamiento, Prioridad, ReglaDominioException};

$datos = ['tenant' => 'HOSP-GUA-01', 'codigo_paciente' => 'PAC-00451', 'servicio' => 'MED-INT',
          'origen' => 'ingreso-resultados-laboratorio', 'id_origen' => 'LAB-77801', 'prioridad' => 'CRITICA',
          'descripcion' => 'Potasio sérico 6.8 mmol/L (ref. 3.5-5.1)'];
$h = fn (string $hora) => new DateTimeImmutable("2026-10-29 $hora:00");
$enviada = function () use ($datos, $h): Alerta {
    $a = Alerta::crear($datos, $h('10:00'));
    $a->registrarEnvio(['enf.turno01', 'med.tratante01'], $h('10:00'));
    return $a;
};

$t->prueba('CA-10 / CA-11 límite del plazo CRITICA: 10:14 no vence, 10:15 sí', function () use ($t, $enviada, $h) {
    $p = new PoliticaEscalamiento();
    $a = $enviada();
    $t->igual(false, $p->vencida($a, $h('10:14')));
    $t->igual(true, $p->vencida($a, $h('10:15')));
});

$t->prueba('RN-04 prioridad ALTA usa 30 minutos', function () use ($t, $datos, $h) {
    $a = Alerta::crear(['prioridad' => 'ALTA'] + $datos, $h('10:00'));
    $a->registrarEnvio(['enf.turno01'], $h('10:00'));
    $p = new PoliticaEscalamiento();
    $t->igual([false, true], [$p->vencida($a, $h('10:29')), $p->vencida($a, $h('10:30'))]);
});

$t->prueba('CA-16 RN-11 plazos fuera de 5-60 minutos se rechazan', function () use ($t) {
    $p = new PoliticaEscalamiento();
    foreach ([4, 61] as $malo) {
        $t->lanza(ReglaDominioException::class, fn () => $p->configurarPlazo(Prioridad::CRITICA, $malo));
    }
    $p->configurarPlazo(Prioridad::CRITICA, 5);
    $p->configurarPlazo(Prioridad::ALTA, 60);
    $t->igual([5, 60], [$p->plazoMinutos(Prioridad::CRITICA), $p->plazoMinutos(Prioridad::ALTA)]);
});

$t->prueba('CA-12 RN-06 una alerta acusada no vence', function () use ($t, $enviada, $h) {
    $a = $enviada();
    $a->acusar('enf.turno01', $h('10:05'));
    $t->igual(false, (new PoliticaEscalamiento())->vencida($a, $h('10:30')));
});

$t->prueba('RN-05 una alerta con escalamiento agotado ya no vence', function () use ($t, $enviada, $h) {
    $a = $enviada();
    $a->agotarEscalamiento($h('10:15'));
    $t->igual(false, (new PoliticaEscalamiento())->vencida($a, $h('11:00')));
});

$t->prueba('RN-11 el constructor también valida los plazos', function () use ($t) {
    $t->lanza(ReglaDominioException::class, fn () => new PoliticaEscalamiento(['ALTA' => 90]));
    $t->igual(20, (new PoliticaEscalamiento(['ALTA' => 20]))->plazoMinutos(Prioridad::ALTA));
});
