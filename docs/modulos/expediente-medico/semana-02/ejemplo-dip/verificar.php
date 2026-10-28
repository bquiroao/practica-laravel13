<?php
declare(strict_types=1);

// Evidencia DIP: CA-01..CA-07 con HOSPITAL en memoria y en PDO/SQLite, reloj fijo y reflexión. Datos ficticios (p1..p4, m1, m2, H1).
require __DIR__ . '/antes.php';
require __DIR__ . '/despues.php';

$fallos = 0;
function verificar(bool $ok, string $texto): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . $texto . "\n"; }

$pacientes = ['p1' => ['estado' => 'active', 'vinculado' => true], 'p2' => ['estado' => 'active', 'vinculado' => false],
              'p3' => ['estado' => 'inactive', 'vinculado' => true], 'p4' => ['estado' => 'active', 'vinculado' => true]];
$asignaciones = ['m1->p1', 'm1->p2', 'm1->p4'];
$pdo = new PDO('sqlite::memory:');
$hospitalPdo = new ExpedientesPdo($pdo);
foreach ($pacientes as $id => $p) $pdo->prepare('INSERT INTO pacientes VALUES (?, ?, ?)')->execute([$id, $p['estado'], (int) $p['vinculado']]);
foreach ($asignaciones as $a) { [$m, $p] = explode('->', $a); $pdo->prepare("INSERT INTO asignaciones VALUES (?, ?, 'active')")->execute([$p, $m]); }

foreach (['memoria' => new ExpedientesEnMemoria($pacientes, $asignaciones), 'PDO/SQLite' => $hospitalPdo] as $nombre => $hospital) {
    $central = new IndiceCentralEnMemoria(['p1@H1' => 5000]);
    $auditoria = new AuditoriaEnMemoria();
    $reloj = new RelojFijo(1000);
    $s = new ServicioExpediente($hospital, $central, $auditoria, $reloj, 'H1');
    $casos = [   // [CA, descripción, resultado, esperado]
        ['CA-01', 'abrir p1 (activo)', $s->abrir('p1')['status'], 201],
        ['CA-01', 'abrir p1 otra vez', $s->abrir('p1')['status'], 409],
        ['CA-01', 'abrir p3 (inactivo)', $s->abrir('p3')['status'], 422],
        ['CA-02', 'm1 consulta p1 (asignación activa)', $s->consultar('p1', 'm1')['status'], 200],
        ['CA-02', 'm2 consulta p1 (sin asignación)', $s->consultar('p1', 'm2')['status'], 403],
        ['CA-03', 'historia externa de p2 (sin vínculo al MPI)', $s->historiaExterna('p2', 'm1'), ['status' => 200, 'solo_local' => true]],
        ['CA-03', 'historia externa de p1 (con consentimiento)', $s->historiaExterna('p1', 'm1')['status'], 200],
        ['CA-04', 'historia externa de p4 (sin consentimiento)', $s->historiaExterna('p4', 'm1')['status'], 403],
        ['CA-05', 'emergencia para p4 sin justificación', $s->emergencia('p4', 'm1', '  ')['status'], 422],
        ['CA-05', 'emergencia para p4 con justificación', $s->emergencia('p4', 'm1', 'paro cardiaco'), ['status' => 200, 'vence' => 4600]],
    ];
    $reloj->t = 6000;
    $casos[] = ['CA-06', 'consentimiento de p1 vencido (t=6000)', $s->historiaExterna('p1', 'm1')['status'], 403];
    foreach ($casos as [$ca, $texto, $real, $esperado]) verificar($real === $esperado, "[$nombre] $ca $texto = " . json_encode($real));
    verificar(($central->accesos[0]['hasta'] ?? 0) - ($central->accesos[0]['desde'] ?? 0) === 3600 && $central->accesos[0]['revision'] === 'pending', "[$nombre] CA-05 acceso de emergencia de 1 hora con revisión pendiente");
    verificar(in_array('403 denegado m2->p1', $auditoria->eventos, true) && count($auditoria->eventos) === 11, "[$nombre] CA-07 auditoría: 11 eventos, uno por operación, incluidos los rechazos");
}

$tipos = array_map(fn ($p) => $p->getType()->getName(), (new ReflectionMethod(ServicioExpediente::class, '__construct'))->getParameters());
$objetos = array_values(array_filter($tipos, fn ($t) => $t !== 'string'));
verificar(array_filter($objetos, fn ($t) => !interface_exists($t)) === [], 'ServicioExpediente depende solo de interfaces: ' . implode(', ', $objetos));
verificar((new ReflectionMethod(ServicioExpedienteAntes::class, '__construct'))->getNumberOfParameters() === 0, 'ServicioExpedienteAntes no recibe dependencias: las crea');
$fuente = file_get_contents(__DIR__ . '/antes.php');
verificar(str_contains($fuente, 'new PDO(') && str_contains($fuente, 'new IndiceCentralHttp()') && str_contains($fuente, 'time()'), 'ServicioExpedienteAntes usa new PDO(), new IndiceCentralHttp() y time()');

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
