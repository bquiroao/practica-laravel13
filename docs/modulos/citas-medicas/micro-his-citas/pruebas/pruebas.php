<?php
declare(strict_types=1);

// El mismo guion (camino feliz y reglas) contra los adaptadores PDO e InMemory, error de persistencia, MVC y dependencias. Datos ficticios.
require __DIR__ . '/../bootstrap.php';

use MicroHis\Citas\Aplicacion\{ErrorPersistencia, HorarioTomado, Reloj, RepositorioCitas};
use MicroHis\Citas\Dominio\Horario;
use MicroHis\Citas\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use MicroHis\Citas\Presentacion\Rutas;
use function MicroHis\Citas\crearControlador;

$fallos = 0;
function prueba(string $nombre, bool $ok): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . "$nombre\n"; }
function baseDePrueba(): PDO { $db = new PDO('sqlite::memory:'); $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    $db->exec("INSERT INTO doctor_specialties VALUES ('m1', 'cardio', 'active'), ('m2', 'cardio', 'active'), ('m3', 'cardio', 'inactive')"); return $db; }
$reloj = new class implements Reloj { public function ahora(): int { return 1000; } };   // «ahora» fijo para probar R-01
$cita = fn (string $m, string $p, int $i, int $f, string $mot = 'control') => ['medico' => $m, 'paciente' => $p, 'especialidad' => 'cardio', 'inicio' => $i, 'fin' => $f, 'motivo' => $mot];
$error = fn (array $r) => "{$r['status']} " . ($r['error'] ?? '');

// 1 y 2. Camino feliz y reglas de dominio: el mismo guion con cada adaptador (contrato del Repository).
$db = baseDePrueba();
$memoria = new RepositorioEnMemoria([['m1', 'cardio'], ['m2', 'cardio']]);   // m3 sin especialidad vigente, como en la base
foreach (['PDO' => new RepositorioPdo($db), 'InMemory' => $memoria] as $adaptador => $repo) {
    $app = crearControlador($repo, $reloj);
    $r = $app->atender('solicitar', $cita('m1', 'p1', 2000, 3000));
    prueba("[$adaptador] feliz: solicitar m1/p1 en [2000,3000) = 201 con id 1", $r === ['status' => 201, 'id' => 1]);
    prueba("[$adaptador] feliz: confirmar y luego cancelar con motivo = 200 y 200",
           $app->atender('confirmar', ['id' => 1])['status'] === 200 && $app->atender('cancelar', ['id' => 1, 'motivo' => 'viaje del paciente'])['status'] === 200);
    prueba("[$adaptador] feliz: el horario liberado se puede volver a usar (R-08)", $app->atender('solicitar', $cita('m1', 'p2', 2000, 3000))['status'] === 201);
    prueba("[$adaptador] regla: cita en el pasado = 422 E2 (R-01)", $error($app->atender('solicitar', $cita('m2', 'p3', 500, 900))) === '422 E2');
    prueba("[$adaptador] regla: médico sin especialidad vigente = 422 E3 (R-04, R-05)", $error($app->atender('solicitar', $cita('m3', 'p3', 5000, 6000))) === '422 E3');
    prueba("[$adaptador] regla: médico ocupado = 409 E4 (R-02)", $error($app->atender('solicitar', $cita('m1', 'p3', 2500, 3500))) === '409 E4');
    prueba("[$adaptador] regla: paciente ocupado = 409 E5 (R-03)", $error($app->atender('solicitar', $cita('m2', 'p2', 2500, 3500))) === '409 E5');
    prueba("[$adaptador] regla: cancelar una cita ya cancelada = 422 E7 (R-07)", $error($app->atender('cancelar', ['id' => 1, 'motivo' => 'x'])) === '422 E7');
    prueba("[$adaptador] contrato: [2000,3000) y [3000,4000) no se cruzan; cambiar desde un estado viejo devuelve false",
           $repo->cruce('m1', 'p9', 3000, 4000) === null && $repo->cambiarEstado(2, 'confirmed', 'cancelled', 'x') === false);
    try { $repo->guardar('m1', 'p8', 'cardio', Horario::validar(2500, 2600, 1000), 'x'); $tomado = false; } catch (HorarioTomado) { $tomado = true; }
    prueba("[$adaptador] cambio S6: guardar sobre un horario ya tomado lanza HorarioTomado (barrera de E6, D7)", $tomado);
}
prueba('[PDO e InMemory] feliz: el motivo queda en appointment_cancellations y en las cancelaciones en memoria (R-10)',
       $db->query('SELECT reason FROM appointment_cancellations')->fetchColumn() === 'viaje del paciente' && $memoria->cancelaciones() === [['cita' => 1, 'motivo' => 'viaje del paciente']]);

// 3. Error de persistencia: un doble que simula la base caída y una PDOException real.
$caida = new class implements RepositorioCitas {
    public function medicoVigente(string $m, string $e): bool { throw new ErrorPersistencia('SQLSTATE[08006] conexión perdida'); }
    public function cruce(string $m, string $p, int $i, int $f): ?string { return null; }
    public function guardar(string $m, string $p, string $e, Horario $h, string $mot): int { return 0; }
    public function estado(int $id): ?string { return null; }
    public function cambiarEstado(int $id, string $de, string $a, ?string $mot): bool { return false; }
};
$r = crearControlador($caida, $reloj)->atender('solicitar', $cita('m1', 'p9', 8000, 9000));
prueba('persistencia (doble): base caída = 503 E8 sin detalles técnicos', $error($r) === '503 E8' && !str_contains($r['mensaje'], 'SQLSTATE'));
$rota = baseDePrueba(); $rota->exec('DROP TABLE appointments');
prueba('persistencia (PDO real): PDOException se traduce a 503 E8', $error(crearControlador(new RepositorioPdo($rota), $reloj)->atender('solicitar', $cita('m1', 'p9', 8000, 9000))) === '503 E8');

// 4. Entrada MVC: rutas, controlador sin SQL ni reglas, y regla de dependencias.
prueba('MVC: las rutas llevan a su acción con el id de la ruta, y una ruta desconocida responde 404', [Rutas::resolver('POST', '/citas'), Rutas::resolver('POST', '/citas/7/cancelacion'), Rutas::resolver('GET', '/citas')]
       === [['solicitar', []], ['cancelar', ['id' => 7]], ['', []]] && crearControlador(new RepositorioEnMemoria(), $reloj)->atender('', [])['status'] === 404);
prueba('MVC: el controlador no tiene SQL, PDO, horarios, estados de cita ni throw', !preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b|PDO|Horario|EstadoCita|throw /', file_get_contents(__DIR__ . '/../src/Presentacion/ControladorCitas.php')));
foreach (['Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'], 'Aplicacion' => ['Persistencia', 'Presentacion'],
          'Persistencia' => ['Presentacion'], 'Presentacion' => ['Persistencia']] as $capa => $prohibidas) {
    $codigo = implode('', array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php")));
    prueba("dependencias: $capa no usa " . implode(', ', $prohibidas), array_filter($prohibidas, fn ($p) => str_contains($codigo, "MicroHis\\Citas\\$p")) === []);
}
prueba('PDO: ninguna consulta concatena variables en el SQL', !preg_match('/(prepare|query|exec)\(\s*"[^"]*\$/', file_get_contents(__DIR__ . '/../src/Persistencia/RepositorioPdo.php')));

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
