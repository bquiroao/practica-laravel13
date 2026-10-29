<?php
declare(strict_types=1);

// Evidencia de la semana 7. Uso: php verificar.php   (sale con 1 si algo no da lo esperado)
require __DIR__ . '/../../micro-his-medicos/bootstrap.php';
require __DIR__ . '/antes.php';
require __DIR__ . '/despues.php';

use Medicos\Aplicacion\Usuario;
use Medicos\Dominio\Vigencia;
use Medicos\Persistencia\MedicosPdo;

$fallas = 0;
function ver(string $id, bool $ok, string $detalle): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $detalle); }
function cuerpo(string $clase): string { $m = new ReflectionMethod($clase, 'ejecutar'); return implode('', array_slice(file($m->getFileName()), $m->getStartLine(), $m->getEndLine() - $m->getStartLine() - 1)); }
function decisiones(string $codigo): int { return preg_match_all('/\b(if|for|foreach|array_filter)\b/', $codigo); }

echo "== Mismo comportamiento con la base real (esquema y datos de demostración)\n";
$pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents(__DIR__ . '/../../micro-his-medicos/sql/esquema.sql'));
$bd = new MedicosPdo($pdo); $hoy = fn () => '2026-10-28'; $u = new Usuario('rec', 'recepcion');
$antes = new Medicos\Antes\ConsultarDisponibilidad($bd, $hoy); $despues = new Medicos\Despues\ConsultarDisponibilidad($bd, $hoy);
$casos = [['MI', '2026-11-02', '2026-11-02'], ['MI', '2026-10-28', '2026-11-10'], ['PED', '2026-11-02', '2026-11-08'], ['NO-EXISTE', '2026-11-02', '2026-11-02']];
$iguales = 0; $filas = 0;
foreach ($casos as [$c, $d, $h]) { $a = $antes->ejecutar($c, $d, $h, $u); $iguales += $a === $despues->ejecutar($c, $d, $h, $u) ? 1 : 0; $filas += count($a); }
ver('REF-01', $iguales === count($casos) && $filas > 0, "antes y después devuelven lo mismo en " . count($casos) . " consultas ($filas franjas)");
$errores = array_map(fn ($clase) => (function () use ($clase, $bd, $hoy) { try { (new $clase($bd, $hoy))->ejecutar('MI', '2026-11-02', '2026-11-02', new Usuario('x', 'paciente')); return 'sin error'; } catch (\DomainException $e) { return $e->codigo; } })(), ['Medicos\Antes\ConsultarDisponibilidad', 'Medicos\Despues\ConsultarDisponibilidad']);
ver('REF-02', $errores === ['acceso_denegado', 'acceso_denegado'], 'ambos rechazan un rol sin permiso con acceso_denegado');

echo "== La regla se prueba sin base de datos\n";
$medicos = ['m1' => ['Dr. A', [new Vigencia('2026-11-01', '2026-11-03')]]];
$horarios = ['m1' => [1 => [['08:00', '09:00']]]];   // lunes
$citas = ['m1' => [['2026-11-02 08:00', '2026-11-02 08:30']]];
ver('REF-03', Medicos\Despues\Disponibilidad::calcular($medicos, $horarios, $citas, '2026-11-02', '2026-11-02') === [['medico_id' => 'm1', 'medico' => 'Dr. A', 'inicio' => '2026-11-02 08:30']], 'una cita de 08:00 deja solo la franja de 08:30');
ver('REF-04', Medicos\Despues\Disponibilidad::calcular($medicos, $horarios, [], '2026-11-09', '2026-11-09') === [], 'fuera de la vigencia de la especialidad no hay franjas');

echo "== Acoplamiento del caso de uso\n";
[$a, $d] = [cuerpo('Medicos\Antes\ConsultarDisponibilidad'), cuerpo('Medicos\Despues\ConsultarDisponibilidad')];
[$la, $ld] = [substr_count(trim($a), "\n") + 1, substr_count(trim($d), "\n") + 1];
ver('REF-05', decisiones($d) === 0 && decisiones($a) >= 4, "decisiones en el caso de uso: antes " . decisiones($a) . ", después " . decisiones($d) . "; líneas: $la → $ld");
$pura = (new ReflectionMethod('Medicos\Despues\Disponibilidad', 'calcular'));
$fuente = implode('', array_slice(file($pura->getFileName()), $pura->getStartLine() - 1, $pura->getEndLine() - $pura->getStartLine() + 1));
ver('REF-06', !preg_match('/PDO|MedicosPdo|\$this|->bd|exigirRol/', $fuente), 'Disponibilidad::calcular no toca PDO, rol ni estado: solo recibe datos');
printf("\nTotal: %d  Pasan: %d  Fallan: %d\n", 6, 6 - $fallas, $fallas);
exit($fallas === 0 ? 0 : 1);
