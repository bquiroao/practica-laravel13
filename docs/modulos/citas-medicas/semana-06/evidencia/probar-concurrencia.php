<?php
declare(strict_types=1);

// Cambio práctico de la semana 6: dos recepcionistas piden el mismo horario a la vez. Un repositorio intercala la segunda
// solicitud entre la consulta de cruces y el INSERT. El mismo guion se corrió antes y después del cambio. Datos ficticios.
require __DIR__ . '/../../micro-his-citas/bootstrap.php';

use MicroHis\Citas\Aplicacion\{Reloj, RepositorioCitas};
use MicroHis\Citas\Dominio\Horario;
use MicroHis\Citas\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use function MicroHis\Citas\crearControlador;

$reloj = new class implements Reloj { public function ahora(): int { return 1000; } };
$cita = fn (string $p) => ['medico' => 'm1', 'paciente' => $p, 'especialidad' => 'cardio', 'inicio' => 5000, 'fin' => 6000, 'motivo' => 'control'];
$resultado = fn (array $r) => rtrim("{$r['status']} " . ($r['error'] ?? ''));
$db = new PDO('sqlite::memory:');
$db->exec(file_get_contents(__DIR__ . '/../../micro-his-citas/database/schema.sql'));
$db->exec("INSERT INTO doctor_specialties VALUES ('m1', 'cardio', 'active')");
foreach (['PDO' => new RepositorioPdo($db), 'InMemory' => new RepositorioEnMemoria([['m1', 'cardio']])] as $adaptador => $real) {
    $segunda = null;
    $otra = function () use ($real, $reloj, $cita, &$segunda) { $segunda = crearControlador($real, $reloj)->atender('solicitar', $cita('p2')); };
    $intercalado = new class($real, $otra) implements RepositorioCitas {
        private bool $hecho = false;
        public function __construct(private RepositorioCitas $r, private Closure $otra) {}
        public function medicoVigente(string $m, string $e): bool { return $this->r->medicoVigente($m, $e); }
        public function cruce(string $m, string $p, int $i, int $f): ?string { $c = $this->r->cruce($m, $p, $i, $f); if (!$this->hecho) { $this->hecho = true; ($this->otra)(); } return $c; }
        public function guardar(string $m, string $p, string $e, Horario $h, string $mot): int { return $this->r->guardar($m, $p, $e, $h, $mot); }
        public function estado(int $id): ?string { return $this->r->estado($id); }
        public function cambiarEstado(int $id, string $de, string $a, ?string $mot): bool { return $this->r->cambiarEstado($id, $de, $a, $mot); }
    };
    $primera = crearControlador($intercalado, $reloj)->atender('solicitar', $cita('p1'));
    $activas = count(array_filter([1, 2], fn (int $id) => $real->estado($id) === 'scheduled'));
    echo "[$adaptador] primera solicitud (p1) = {$resultado($primera)} | segunda, intercalada (p2) = {$resultado($segunda)} | citas activas de m1 en [5000,6000): $activas\n";
}
