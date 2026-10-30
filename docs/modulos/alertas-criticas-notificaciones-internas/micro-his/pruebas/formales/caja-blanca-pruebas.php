<?php
/**
 * Semana 15 · Pruebas de CAJA BLANCA: cubren las ramas de NotificarNivel::ejecutar y Alerta::acusar.
 * Ramas de NotificarNivel: (a) sin destinatarios y nivel<2; (b) sin destinatarios y nivel=2; (c) sin fallidos;
 * (d) fallidos y nivel<2; (e) fallidos, nivel=2 y alguien recibió; (f) fallidos, nivel=2 y nadie recibió.
 * @var Pruebas $t
 */
declare(strict_types=1);

use AlertasCriticas\Application\{DespachadorNotificaciones, NotificarNivel};
use AlertasCriticas\Domain\{Alerta, ReglaDominioException};
use AlertasCriticas\Persistence\{DirectorioDestinatariosEnMemoria, NotificadorSimulado};

$ahora = new DateTimeImmutable('2026-10-29 10:00');
$correr = function (array $niveles, array $fallan) use ($ahora): Alerta {
    $a = Alerta::crear(['tenant' => 'T', 'codigo_paciente' => 'PAC-00451', 'servicio' => 'S', 'origen' => 'lab',
        'id_origen' => 'L1', 'prioridad' => 'CRITICA', 'descripcion' => 'x'], $ahora);
    (new NotificarNivel(new DirectorioDestinatariosEnMemoria(['T' => ['S' => $niveles]]),
        new DespachadorNotificaciones(new NotificadorSimulado($fallan))))->ejecutar($a, $ahora);
    return $a;
};
$ev = fn (Alerta $a) => implode(' > ', array_map(fn ($e) => $e->evento, $a->historial()));

$t->prueba('CB-01 ramas (a)(a)(b): ningún nivel tiene destinatarios → sube hasta 2 y agota', function () use ($t, $correr, $ev) {
    $a = $correr([0 => [], 1 => [], 2 => []], []);
    $t->igual([2, true, 'CREADA'], [$a->nivel(), $a->escalamientoAgotado(), $a->estado()->value]);
    $t->igual('creada > sin destinatarios > escalada > sin destinatarios > escalada > sin destinatarios > escalamiento agotado', $ev($a));
});

$t->prueba('CB-02 ramas (d)(d)(e): fallan nivel 0 y 1; en nivel 2 uno recibe y otro falla → termina sin agotar', function () use ($t, $correr) {
    $a = $correr([0 => ['e0'], 1 => ['j1'], 2 => ['c2', 'c3']], ['e0', 'j1', 'c3']);
    $t->igual([2, false, 'ESCALADA', ['c2' => 2]], [$a->nivel(), $a->escalamientoAgotado(), $a->estado()->value, $a->destinatarios()]);
});

$t->prueba('CB-03 rama (f): en nivel 2 nadie recibe → agota', function () use ($t, $correr) {
    $a = $correr([0 => ['e0'], 1 => ['j1'], 2 => ['c2']], ['e0', 'j1', 'c2']);
    $t->igual([2, true], [$a->nivel(), $a->escalamientoAgotado()]);
});

$t->prueba('CB-04 rama (c): todos reciben en nivel 0 → ENVIADA sin subir', function () use ($t, $correr, $ev) {
    $a = $correr([0 => ['e0', 'm0']], []);
    $t->igual([0, 'ENVIADA', 'creada > enviada'], [$a->nivel(), $a->estado()->value, $ev($a)]);
});

$t->prueba('CB-05 Alerta::acusar cubre sus 4 ramas: ya acusada, no enviada, no notificado y primer acuse', function () use ($t, $ahora) {
    $nueva = fn () => Alerta::crear(['tenant' => 'T', 'codigo_paciente' => 'PAC-00451', 'servicio' => 'S', 'origen' => 'lab',
        'id_origen' => 'L1', 'prioridad' => 'CRITICA', 'descripcion' => 'x'], $ahora);
    $creada = $nueva();
    $e1 = $t->lanza(ReglaDominioException::class, fn () => $creada->acusar('u', $ahora));      // no enviada
    $enviada = $nueva();
    $enviada->registrarEnvio(['u'], $ahora);
    $e2 = $t->lanza(ReglaDominioException::class, fn () => $enviada->acusar('otro', $ahora));  // no notificado
    $primero = $enviada->acusar('u', $ahora);                                                  // primer acuse
    $repetido = $enviada->acusar('u', $ahora);                                                 // ya acusada
    $t->igual(['RN-07', 'RN-07', true, false], [$e1->regla, $e2->regla, $primero, $repetido]);
    $t->verdadero(str_contains($e1->getMessage(), 'todavía no fue enviada'), $e1->getMessage());
});
