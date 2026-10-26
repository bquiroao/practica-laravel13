<?php
declare(strict_types=1);

// Evidencia DIP: (1) la misma política pasa los mismos criterios con dos detalles distintos
// (memoria y PDO/SQLite) y un reloj fijo; (2) por reflexión, solo depende de interfaces;
// (3) el diseño anterior crea sus detalles y lee la hora del sistema.
require __DIR__ . '/antes.php';
require __DIR__ . '/despues.php';

$fallos = 0;
function verificar(bool $ok, string $texto): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . $texto . "\n"; }

foreach (['memoria' => new AgendaEnMemoria(), 'PDO/SQLite' => new AgendaPdo(new PDO('sqlite::memory:'))] as $nombre => $agenda) {
    $auditoria = new AuditoriaEnMemoria();
    $s = new ServicioCitas($agenda, new RelojFijo(1000), $auditoria);
    $casos = [   // [CA, descripción, resultado, esperado]
        ['CA-01', 'cita m1/p1 en [2000,3000)', $s->solicitar('m1', 'p1', 2000, 3000, 'control'), null],
        ['CA-02', 'cita en el pasado', $s->solicitar('m1', 'p1', 500, 900, 'control'), 'E2'],
        ['CA-02', 'fin antes del inicio', $s->solicitar('m1', 'p1', 4000, 3500, 'control'), 'E2'],
        ['CA-02', 'sin motivo', $s->solicitar('m1', 'p1', 5000, 6000, ' '), 'E2'],
        ['CA-03', 'médico ocupado (m1, otro paciente)', $s->solicitar('m1', 'p2', 2500, 3500, 'control'), 'E4'],
        ['CA-04', 'paciente ocupado (otro médico, p1)', $s->solicitar('m2', 'p1', 2500, 3500, 'control'), 'E5'],
        ['CA-05', 'm1/p2 justo al terminar [3000,4000)', $s->solicitar('m1', 'p2', 3000, 4000, 'control'), null],
        ['CA-06', 'cancelar cita 1 sin motivo', $s->cancelar(1, ''), 'E2'],
        ['CA-06', 'cancelar cita 1 con motivo', $s->cancelar(1, 'viaje del paciente'), null],
        ['CA-07', 'cancelar otra vez la cita 1', $s->cancelar(1, 'x'), 'E7'],
        ['CA-08', 'el horario liberado se puede usar', $s->solicitar('m1', 'p3', 2000, 3000, 'control'), null],
        ['CA-07', 'cancelar cita inexistente', $s->cancelar(99, 'x'), 'NF'],
    ];
    foreach ($casos as [$ca, $texto, $real, $esperado]) {
        verificar($real === $esperado, sprintf('[%s] %s %s = %s', $nombre, $ca, $texto, $real ?? 'aceptada'));
    }
    verificar($auditoria->eventos === ['cancelada 1: viaje del paciente'], "[$nombre] auditoría con el motivo de cancelación");
}

$tipos = array_map(fn ($p) => $p->getType()->getName(), (new ReflectionMethod(ServicioCitas::class, '__construct'))->getParameters());
verificar(array_filter($tipos, fn ($t) => !interface_exists($t)) === [], 'ServicioCitas depende solo de interfaces: ' . implode(', ', $tipos));
verificar((new ReflectionMethod(ServicioCitasAntes::class, '__construct'))->getNumberOfParameters() === 0, 'ServicioCitasAntes no recibe dependencias: las crea');
$fuente = file_get_contents(__DIR__ . '/antes.php');
verificar(str_contains($fuente, 'new PDO(') && str_contains($fuente, 'time()'), 'ServicioCitasAntes usa new PDO() y time() directamente');

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
