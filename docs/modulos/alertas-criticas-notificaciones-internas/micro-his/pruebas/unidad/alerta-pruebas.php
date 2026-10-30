<?php
/** Reglas de dominio de la entidad Alerta y la política de escalamiento. @var Pruebas $t */
declare(strict_types=1);

use AlertasCriticas\Domain\{Alerta, EstadoAlerta, ReglaDominioException};

$datos = ['tenant' => 'HOSP-GUA-01', 'codigo_paciente' => 'PAC-00451', 'servicio' => 'MED-INT',
          'origen' => 'ingreso-resultados-laboratorio', 'id_origen' => 'LAB-77801', 'prioridad' => 'CRITICA',
          'descripcion' => 'Potasio sérico 6.8 mmol/L (ref. 3.5-5.1)'];
$h = fn (string $hora) => new DateTimeImmutable("2026-10-29 $hora:00");
$enviada = function () use ($datos, $h): Alerta {
    $a = Alerta::crear($datos, $h('10:00'));
    $a->registrarEnvio(['enf.turno01', 'med.tratante01'], $h('10:00'));
    return $a;
};

$t->prueba('CA-01 crear deja la alerta CREADA con evento «creada»', function () use ($t, $datos, $h) {
    $a = Alerta::crear($datos, $h('10:00'));
    $t->igual(EstadoAlerta::CREADA, $a->estado());
    $t->igual('creada', $a->historial()[0]->evento);
    $t->igual('sistema:ingreso-resultados-laboratorio', $a->historial()[0]->actor);
});

$t->prueba('CA-02 RN-01 sin código de paciente se rechaza', function () use ($t, $datos, $h) {
    $e = $t->lanza(ReglaDominioException::class, fn () => Alerta::crear(['codigo_paciente' => ''] + $datos, $h('10:00')));
    $t->igual('RN-01', $e->regla);
    $t->verdadero(str_contains($e->getMessage(), '«codigo_paciente»'), 'El mensaje debe nombrar el campo');
});

$t->prueba('RN-01 código de paciente con formato inválido se rechaza', function () use ($t, $datos, $h) {
    $t->lanza(ReglaDominioException::class, fn () => Alerta::crear(['codigo_paciente' => 'Juan'] + $datos, $h('10:00')));
});

$t->prueba('RN-01 prioridad desconocida se rechaza', function () use ($t, $datos, $h) {
    $t->lanza(ReglaDominioException::class, fn () => Alerta::crear(['prioridad' => 'BAJA'] + $datos, $h('10:00')));
});

$t->prueba('CA-05 registrarEnvio en nivel 0 deja ENVIADA y guarda destinatarios', function () use ($t, $enviada) {
    $a = $enviada();
    $t->igual(EstadoAlerta::ENVIADA, $a->estado());
    $t->igual(['enf.turno01' => 0, 'med.tratante01' => 0], $a->destinatarios());
});

$t->prueba('CA-07 acusar registra usuario y hora', function () use ($t, $enviada, $h) {
    $a = $enviada();
    $t->verdadero($a->acusar('enf.turno01', $h('10:05')));
    $ultimo = $a->historial()[count($a->historial()) - 1];
    $t->igual(['acusada', 'enf.turno01', '10:05'], [$ultimo->evento, $ultimo->actor, $ultimo->momento->format('H:i')]);
});

$t->prueba('CA-08 RN-06 acusar dos veces no duplica el evento', function () use ($t, $enviada, $h) {
    $a = $enviada();
    $a->acusar('enf.turno01', $h('10:05'));
    $t->igual(false, $a->acusar('med.tratante01', $h('10:06')));
    $acuses = array_filter($a->historial(), fn ($e) => $e->evento === 'acusada');
    $t->igual(1, count($acuses));
});

$t->prueba('CA-09 RN-07 un usuario no notificado no puede acusar', function () use ($t, $enviada, $h) {
    $a = $enviada();
    $e = $t->lanza(ReglaDominioException::class, fn () => $a->acusar('enf.otroservicio', $h('10:05')));
    $t->igual('RN-07', $e->regla);
    $t->igual(EstadoAlerta::ENVIADA, $a->estado());
});

$t->prueba('CA-12 RN-06 una alerta acusada no se escala', function () use ($t, $enviada, $h) {
    $a = $enviada();
    $a->acusar('enf.turno01', $h('10:05'));
    $t->lanza(ReglaDominioException::class, fn () => $a->subirNivel('plazo vencido', $h('10:30')));
});

$t->prueba('CA-13 RN-05 en nivel 2 no se sube más y el agotamiento se marca una vez', function () use ($t, $enviada, $h) {
    $a = $enviada();
    $a->subirNivel('plazo vencido', $h('10:15'));
    $a->subirNivel('plazo vencido', $h('10:30'));
    $t->lanza(ReglaDominioException::class, fn () => $a->subirNivel('plazo vencido', $h('10:45')));
    $a->agotarEscalamiento($h('10:45'));
    $a->agotarEscalamiento($h('11:00'));
    $agotados = array_filter($a->historial(), fn ($e) => $e->evento === 'escalamiento agotado');
    $t->igual([2, 1], [$a->nivel(), count($agotados)]);
});

$t->prueba('CA-14 RN-09 cerrar exige acuse previo y nota', function () use ($t, $enviada, $h) {
    $a = $enviada();
    $t->lanza(ReglaDominioException::class, fn () => $a->cerrar('med.tratante01', 'Atendida', $h('10:10')));
    $a->acusar('med.tratante01', $h('10:05'));
    $t->lanza(ReglaDominioException::class, fn () => $a->cerrar('med.tratante01', '   ', $h('10:10')));
    $a->cerrar('med.tratante01', 'Se indicó gluconato de calcio (ficticio)', $h('10:10'));
    $t->igual(EstadoAlerta::CERRADA, $a->estado());
});

$t->prueba('CA-15 RN-10 historial completo y ordenado', function () use ($t, $enviada, $h) {
    $a = $enviada();
    $a->acusar('med.tratante01', $h('10:05'));
    $a->cerrar('med.tratante01', 'Atendida', $h('10:10'));
    $t->igual(['creada', 'enviada', 'acusada', 'cerrada'], array_map(fn ($e) => $e->evento, $a->historial()));
    $t->igual([1, 2, 3, 4], array_map(fn ($e) => $e->orden, $a->historial()));
});

$t->prueba('RNF-02 el resumen lleva el código del paciente y no nombres', function () use ($t, $enviada) {
    $t->verdadero(str_starts_with($enviada()->resumen(), '[CRITICA] PAC-00451 · Potasio'));
});
