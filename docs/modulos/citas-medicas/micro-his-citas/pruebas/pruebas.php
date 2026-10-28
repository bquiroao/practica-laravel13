<?php
declare(strict_types=1);

// Camino feliz, reglas de dominio y error de persistencia (base SQLite de prueba y un doble), más la regla de dependencias. Datos ficticios.
require __DIR__ . '/../bootstrap.php';

use MicroHis\Citas\Aplicacion\{ErrorPersistencia, Reloj, RepositorioCitas, ServicioCitas};
use MicroHis\Citas\Dominio\Horario;
use MicroHis\Citas\Presentacion\ControladorCitas;

$fallos = 0;
function prueba(string $nombre, bool $ok): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . "$nombre\n"; }
function baseDePrueba(): PDO { $db = new PDO('sqlite::memory:'); $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    $db->exec("INSERT INTO doctor_specialties VALUES ('m1', 'cardio', 'active'), ('m2', 'cardio', 'active'), ('m3', 'cardio', 'inactive')"); return $db; }
$reloj = new class implements Reloj { public function ahora(): int { return 1000; } };   // «ahora» fijo para probar R-01
$cita = fn (string $m, string $p, int $i, int $f, string $mot = 'control') => ['medico' => $m, 'paciente' => $p, 'especialidad' => 'cardio', 'inicio' => $i, 'fin' => $f, 'motivo' => $mot];
$error = fn (array $r) => "{$r['status']} " . ($r['error'] ?? '');

// 1. Camino feliz, de la presentación a la base de prueba.
$db = baseDePrueba();
$app = crearControlador($db, $reloj);
$r = $app->atender('solicitar', $cita('m1', 'p1', 2000, 3000));
prueba('feliz: solicitar m1/p1 en [2000,3000) = 201 con id', $r['status'] === 201 && $r['id'] === 1);
prueba('feliz: confirmar la cita 1 = 200', $app->atender('confirmar', ['id' => 1])['status'] === 200);
prueba('feliz: cancelar la cita 1 con motivo = 200', $app->atender('cancelar', ['id' => 1, 'motivo' => 'viaje del paciente'])['status'] === 200);
prueba('feliz: el motivo queda registrado (R-10)', $db->query('SELECT reason FROM appointment_cancellations')->fetchColumn() === 'viaje del paciente');
prueba('feliz: el horario liberado se puede volver a usar (R-08)', $app->atender('solicitar', $cita('m1', 'p2', 2000, 3000))['status'] === 201);
// 2. Reglas de dominio.
prueba('regla: cita en el pasado = 422 E2 (R-01)', $error($app->atender('solicitar', $cita('m2', 'p3', 500, 900))) === '422 E2');
prueba('regla: médico sin especialidad vigente = 422 E3 (R-04, R-05)', $error($app->atender('solicitar', $cita('m3', 'p3', 5000, 6000))) === '422 E3');
prueba('regla: médico ocupado = 409 E4 (R-02)', $error($app->atender('solicitar', $cita('m1', 'p3', 2500, 3500))) === '409 E4');
prueba('regla: paciente ocupado = 409 E5 (R-03)', $error($app->atender('solicitar', $cita('m2', 'p2', 2500, 3500))) === '409 E5');
prueba('regla: cancelar una cita ya cancelada = 422 E7 (R-07)', $error($app->atender('cancelar', ['id' => 1, 'motivo' => 'x'])) === '422 E7');

// 3. Error de persistencia: un doble que simula la base caída y una PDOException real.
$caida = new class implements RepositorioCitas {
    public function medicoVigente(string $m, string $e): bool { throw new ErrorPersistencia('SQLSTATE[08006] conexión perdida'); }
    public function cruce(string $m, string $p, int $i, int $f): ?string { return null; }
    public function guardar(string $m, string $p, string $e, Horario $h, string $mot): int { return 0; }
    public function estado(int $id): ?string { return null; }
    public function cambiarEstado(int $id, string $de, string $a, ?string $mot): bool { return false; }
};
$r = (new ControladorCitas(new ServicioCitas($caida, $reloj)))->atender('solicitar', $cita('m1', 'p9', 8000, 9000));
prueba('persistencia (doble): base caída = 503 E8 sin detalles técnicos', $error($r) === '503 E8' && !str_contains($r['mensaje'], 'SQLSTATE'));
$rota = baseDePrueba(); $rota->exec('DROP TABLE appointments');
prueba('persistencia (PDO real): PDOException se traduce a 503 E8', $error(crearControlador($rota, $reloj)->atender('solicitar', $cita('m1', 'p9', 8000, 9000))) === '503 E8');
// 4. Regla de dependencias: cada capa solo apunta hacia adentro.
foreach (['Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'], 'Aplicacion' => ['Persistencia', 'Presentacion'],
          'Persistencia' => ['Presentacion'], 'Presentacion' => ['Persistencia']] as $capa => $prohibidas) {
    $codigo = implode('', array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php")));
    prueba("dependencias: $capa no usa " . implode(', ', $prohibidas), array_filter($prohibidas, fn ($p) => str_contains($codigo, "MicroHis\\Citas\\$p")) === []);
}
prueba('PDO: ninguna consulta concatena variables en el SQL', !preg_match('/(prepare|query|exec)\(\s*"[^"]*\$/', file_get_contents(__DIR__ . '/../src/Persistencia/RepositorioPdo.php')));

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
