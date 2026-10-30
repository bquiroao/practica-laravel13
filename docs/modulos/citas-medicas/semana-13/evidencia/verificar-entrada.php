<?php
declare(strict_types=1);

// Autorrevisión del autor (criterio de entrada E-03): corre las pruebas del Micro-HIS y reproduce cada hallazgo
// sobre el código tal como está. Si alguien corrige un hallazgo, su línea pasa a «no reproducido». Datos ficticios.
$raiz = __DIR__ . '/../../micro-his-citas';
require $raiz . '/bootstrap.php';

use MicroHis\Citas\Aplicacion\{Reloj, RepositorioCitas, ServicioCitas};
use MicroHis\Citas\Dominio\Horario;
use MicroHis\Citas\Persistencia\RepositorioPdo;
use MicroHis\Citas\Presentacion\{ControladorCitas, VistaJson};
use function MicroHis\Citas\crearControlador;

function linea(string $id, bool $reproducido, string $detalle): void { printf("%-5s %-15s %s\n", $id, $reproducido ? 'reproducido' : 'no reproducido', $detalle); }
function columnas(PDO $db, string $tabla): array { return array_column($db->query("PRAGMA table_info($tabla)")->fetchAll(), 'name'); }

exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$raiz/pruebas/pruebas.php"), $salida, $codigo);
printf("ENTRADA pruebas del Micro-HIS: %s (%s)\n\n", $codigo === 0 ? 'en verde' : 'FALLAN', end($salida));

$db = new PDO('sqlite::memory:');
$db->exec(file_get_contents("$raiz/database/schema.sql"));
$db->exec("INSERT INTO doctor_specialties VALUES ('m1', 'cardio', 'active')");
$reloj = new class implements Reloj { public int $t = 1000; public function ahora(): int { return $this->t; } };
$app = crearControlador(new RepositorioPdo($db), $reloj);
$cita = fn (string $p, int $i, int $f) => ['medico' => 'm1', 'paciente' => $p, 'especialidad' => 'cardio', 'inicio' => $i, 'fin' => $f, 'motivo' => 'control'];
$http = (new ReflectionClassConstant(VistaJson::class, 'HTTP'))->getValue();   // desde la semana 4, la vista es dueña de los códigos

// H-01: R-09 / CU-05. La entrada del controlador no recibe usuario, permiso ni hospital, y no existe E1.
$parametros = array_map(fn ($p) => $p->getName(), (new ReflectionMethod(ControladorCitas::class, 'atender'))->getParameters());
linea('H-01', !isset($http['E1']), 'entrada atender(' . implode(', ', $parametros) . '); códigos: ' . implode(' ', array_keys($http)));

// H-02: CU-01 / D7. Dos recepcionistas piden el mismo horario a la vez: entre la consulta de cruces y el INSERT
// entra la otra solicitud. Un repositorio que intercala esa segunda solicitud reproduce la carrera.
$intercalado = new class(new RepositorioPdo($db), fn () => crearControlador(new RepositorioPdo($db), $reloj)->atender('solicitar', $cita('p2', 5000, 6000))) implements RepositorioCitas {
    private bool $hecho = false;
    public function __construct(private RepositorioCitas $real, private Closure $otra) {}
    public function medicoVigente(string $m, string $e): bool { return $this->real->medicoVigente($m, $e); }
    public function cruce(string $m, string $p, int $i, int $f): ?string { $r = $this->real->cruce($m, $p, $i, $f); if (!$this->hecho) { $this->hecho = true; ($this->otra)(); } return $r; }
    public function guardar(string $m, string $p, string $e, Horario $h, string $mot): int { return $this->real->guardar($m, $p, $e, $h, $mot); }
    public function estado(int $id): ?string { return $this->real->estado($id); }
    public function cambiarEstado(int $id, string $de, string $a, ?string $mot): bool { return $this->real->cambiarEstado($id, $de, $a, $mot); }
};
$r = (new ControladorCitas(new ServicioCitas($intercalado, $reloj), new VistaJson()))->atender('solicitar', $cita('p1', 5000, 6000));
$activas = (int) $db->query("SELECT COUNT(*) FROM appointments WHERE doctor = 'm1' AND starts_at = 5000 AND status = 'scheduled'")->fetchColumn();
linea('H-02', $activas > 1 || $r['status'] !== 409, 'la solicitud que empezó primero = ' . rtrim("{$r['status']} " . ($r['error'] ?? '')) . " (se esperaba 409 E6); citas activas de m1 en [5000,6000): $activas; E6 en el mapa HTTP: " . (isset($http['E6']) ? 'sí' : 'no'));

// H-03: CU-02 dice «ya empezó (E7)», pero confirmar no mira el reloj.
$id = $app->atender('solicitar', $cita('p3', 7000, 8000))['id'];
$reloj->t = 7500;
$r = $app->atender('confirmar', ['id' => $id]);
linea('H-03', $r['status'] === 200, "confirmar a las 7500 una cita de [7000,8000) = " . rtrim("{$r['status']} " . ($r['error'] ?? '')));

// H-04: R-10. La cancelación guarda motivo y fecha, pero no quién canceló.
$cancelaciones = columnas($db, 'appointment_cancellations');
linea('H-04', array_intersect(['user_id', 'cancelled_by'], $cancelaciones) === [], 'appointment_cancellations: ' . implode(', ', $cancelaciones));

// H-05: R-04 pide la especialidad «vigente en la fecha de la cita» y R-05 el perfil activo; solo hay un estado, sin fechas ni perfil.
$especialidades = columnas($db, 'doctor_specialties');
$tablas = $db->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
linea('H-05', array_intersect(['valid_from', 'valid_to'], $especialidades) === [], 'doctor_specialties: ' . implode(', ', $especialidades) . '; tablas: ' . implode(', ', $tablas));
