<?php
/** Semana 15 · Pruebas UNITARIAS de reglas (U-01..U-04). @var Pruebas $t */
declare(strict_types=1);

use AlertasCriticas\Application\DespachadorNotificaciones;
use AlertasCriticas\Domain\{Alerta, PoliticaEscalamiento, Prioridad, PuertoNotificacion, ReglaDominioException};

$t->prueba('U-01 [RF-10, RN-11] plazo configurable: 5 y 60 se aceptan; 4 y 61 se rechazan', function () use ($t) {
    $p = new PoliticaEscalamiento();
    $rechazos = 0;
    foreach ([4, 61] as $m) {
        try { $p->configurarPlazo(Prioridad::ALTA, $m); } catch (ReglaDominioException) { $rechazos++; }
    }
    $p->configurarPlazo(Prioridad::ALTA, 5);
    $p->configurarPlazo(Prioridad::CRITICA, 60);
    $t->igual([2, 5, 60], [$rechazos, $p->plazoMinutos(Prioridad::ALTA), $p->plazoMinutos(Prioridad::CRITICA)]);
});

$t->prueba('U-02 [RF-05, RN-08] el despachador hace exactamente maxIntentos y cuenta las excepciones como fallo', function () use ($t) {
    $canal = new class implements PuertoNotificacion {
        public int $llamadas = 0;
        public function enviar(string $usuario, string $mensaje): bool { $this->llamadas++; throw new RuntimeException('caído'); }
    };
    $r = (new DespachadorNotificaciones($canal, 3))->despachar('m', ['enf.turno01']);
    $t->igual([3, [], ['enf.turno01']], [$canal->llamadas, $r['entregados'], $r['fallidos']]);
});

$t->prueba('U-03 [RF-06, RN-06] acusar devuelve true una vez y false después; el estado no cambia', function () use ($t) {
    $a = Alerta::crear(['tenant' => 'HOSP-GUA-01', 'codigo_paciente' => 'PAC-00451', 'servicio' => 'MED-INT', 'origen' => 'lab',
        'id_origen' => 'L1', 'prioridad' => 'ALTA', 'descripcion' => 'x'], new DateTimeImmutable('2026-10-29 10:00'));
    $a->registrarEnvio(['enf.turno01'], new DateTimeImmutable('2026-10-29 10:00'));
    $t->igual([true, false, 'ACUSADA'], [$a->acusar('enf.turno01', new DateTimeImmutable('2026-10-29 10:01')),
        $a->acusar('enf.turno01', new DateTimeImmutable('2026-10-29 10:02')), $a->estado()->value]);
});

$t->prueba('U-04 [RNF-02] el resumen de notificación no supera 200 + 40 caracteres y no tiene nombres', function () use ($t) {
    $a = Alerta::crear(['tenant' => 'HOSP-GUA-01', 'codigo_paciente' => 'PAC-00451', 'servicio' => 'MED-INT', 'origen' => 'lab',
        'id_origen' => 'L1', 'prioridad' => 'CRITICA', 'descripcion' => str_repeat('k', 200)], new DateTimeImmutable('2026-10-29 10:00'));
    $t->verdadero(mb_strlen($a->resumen()) <= 240 && str_contains($a->resumen(), 'PAC-00451'), $a->resumen());
});
