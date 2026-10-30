<?php
/** RegistrarAlertaCritica con dobles: camino feliz, reglas de dominio y error de persistencia. @var Pruebas $t */
declare(strict_types=1);

require_once __DIR__ . '/../dobles/escenario.php';

use AlertasCriticas\Domain\{ErrorPersistenciaException, EstadoAlerta, PuertoNotificacion};
use AlertasCriticas\Persistence\NotificadorSimulado;

$t->prueba('CAMINO FELIZ CA-01/CA-05 registrar guarda, notifica al nivel 0 y queda ENVIADA', function () use ($t) {
    $e = escenario();
    $r = $e['registrar']->ejecutar(datosAlerta());
    $t->igual([false, EstadoAlerta::ENVIADA], [$r['duplicada'], $r['alerta']->estado()]);
    $t->igual(['enf.turno01', 'med.tratante01'], array_column($e['notificador']->enviados, 'usuario'));
    $t->igual(1, count($e['repo']->alertas));
});

$t->prueba('CA-03 RN-02 el mismo evento de origen no duplica ni reenvía', function () use ($t) {
    $e = escenario();
    $primera = $e['registrar']->ejecutar(datosAlerta())['alerta'];
    $r = $e['registrar']->ejecutar(datosAlerta());
    $t->igual([true, $primera->id, 1, 2], [$r['duplicada'], $r['alerta']->id, count($e['repo']->alertas), count($e['notificador']->enviados)]);
});

$t->prueba('CA-04 RN-03 sin destinatarios en nivel 0 se notifica al nivel 1', function () use ($t) {
    $e = escenario();
    $a = $e['registrar']->ejecutar(datosAlerta(['servicio' => 'PED', 'id_origen' => 'LAB-77802']))['alerta'];
    $t->igual([1, EstadoAlerta::ESCALADA], [$a->nivel(), $a->estado()]);
    $t->igual(['jefe.servicio02'], array_column($e['notificador']->enviados, 'usuario'));
    $t->igual(['creada', 'sin destinatarios', 'escalada', 'enviada'], eventos($a));
});

$t->prueba('CA-06 RN-08 3 intentos al destinatario que falla y escalamiento inmediato', function () use ($t) {
    $notificador = new NotificadorSimulado(['enf.turno01']);
    $e = escenario(null, $notificador);
    $a = $e['registrar']->ejecutar(datosAlerta())['alerta'];
    $t->igual(['enf.turno01' => 3, 'med.tratante01' => 1, 'jefe.servicio01' => 1], $notificador->intentos);
    $t->igual([1, EstadoAlerta::ESCALADA], [$a->nivel(), $a->estado()]);
    $t->igual(['creada', 'enviada', 'entrega fallida', 'escalada', 'enviada'], eventos($a));
});

$t->prueba('RNF-05 si el canal lanza excepción la alerta no se pierde', function () use ($t) {
    $roto = new class implements PuertoNotificacion {
        public function enviar(string $usuario, string $mensaje): bool { throw new RuntimeException('canal caído'); }
    };
    $e = escenario(null, $roto);
    $a = $e['registrar']->ejecutar(datosAlerta())['alerta'];
    $t->igual([true, 2, true], [isset($e['repo']->alertas[$a->id]), $a->nivel(), $a->escalamientoAgotado()]);
});

$t->prueba('ERROR DE PERSISTENCIA: si la base falla no se notifica a nadie', function () use ($t) {
    $e = escenario(new RepositorioDoble(true));
    $t->lanza(ErrorPersistenciaException::class, fn () => $e['registrar']->ejecutar(datosAlerta()));
    $t->igual([], $e['notificador']->enviados);
});

$t->prueba('REGLA DE DOMINIO: datos inválidos no llegan a guardarse', function () use ($t) {
    $e = escenario();
    $t->lanza(\AlertasCriticas\Domain\ReglaDominioException::class,
        fn () => $e['registrar']->ejecutar(datosAlerta(['codigo_paciente' => ''])));
    $t->igual([0, []], [$e['repo']->guardados, $e['notificador']->enviados]);
});
