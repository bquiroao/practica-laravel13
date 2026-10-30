<?php
declare(strict_types=1);

// Semana 6 – respuesta al cambio práctico y comprobación de la matriz:  php demostrar-cambio.php
// Cambio: «Citas (M05) publicará sus citas por su propia API: la disponibilidad debe leerlas de ahí». Se resuelve con UNA fuente nueva
// que implementa FuenteHospital (semana 4): ConsultarDisponibilidad, Agenda y el Repository quedan intactos.
use Medicos\Aplicacion\{ConsultarDisponibilidad, ErrorPersistencia, FuenteHospital, Usuario};
use Medicos\Persistencia\EnMemoria;

$modulo = dirname(__DIR__);
require "$modulo/micro-his-medicos/bootstrap.php";

/** Propuesta (pasaría tal cual a src/Persistencia/): usuario y especialidad vienen de la fuente actual; las citas, de un cliente de la API de Citas. */
final class FuenteHospitalConCitasRemotas implements FuenteHospital {
    /** @param \Closure(array, string, string): array $clienteCitas llama a Citas; si no responde lanza ErrorPersistencia */
    public function __construct(private FuenteHospital $base, private \Closure $clienteCitas) {}
    public function usuarioActivo(string $id): bool { return $this->base->usuarioActivo($id); }
    public function especialidadActiva(string $id): bool { return $this->base->especialidadActiva($id); }
    public function citasActivas(array $ids, string $desde, string $hasta): array { return ($this->clienteCitas)($ids, $desde, $hasta); }
}

$fallas = 0;
function ver(string $que, bool $ok, string $detalle = ''): void { global $fallas; $fallas += $ok ? 0 : 1; printf("%-6s %s%s\n", $ok ? 'PASA' : 'FALLA', $que, $ok || $detalle === '' ? '' : " → $detalle"); }

$pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents("$modulo/micro-his-medicos/sql/esquema.sql"));
$hoy = fn () => '2026-10-28';
$recepcion = new Usuario('rec-1', 'recepcion');
$consultar = fn (EnMemoria $m, FuenteHospital $f) => (new ConsultarDisponibilidad($m, $f, $hoy))->ejecutar('MI', '2026-11-02', '2026-11-02', $recepcion);
$horas = fn (array $franjas) => array_column($franjas, 'inicio');
// Lo que respondería Citas: las mismas citas activas del lunes 2 (la cancelada no viene).
$citasDeM05 = fn (array $ids, string $desde, string $hasta) => ['med-ana' => [['2026-11-02 08:00', '2026-11-02 08:30']]];

// C-01: mismas franjas con las citas de la base y con las que entrega Citas.
$base = EnMemoria::desdePdo($pdo);
$conBase = $consultar($base, $base);
$conCitas = $consultar($base, new FuenteHospitalConCitasRemotas($base, $citasDeM05));
ver('C-01 mismas franjas con las citas de la base y con las de Citas (08:30, 09:00 y 09:30 de la Dra. Ana)', $conBase === $conCitas && $horas($conCitas) === ['2026-11-02 08:30', '2026-11-02 09:00', '2026-11-02 09:30']);

// C-02: si Citas no manda ninguna cita, se ofrecen todas las franjas; la cancelada de la base no ocupa lugar (CA-07).
$sinCitas = $consultar($base, new FuenteHospitalConCitasRemotas($base, fn () => []));
ver('C-02 sin citas desde Citas aparece también la franja de las 08:00 (4 franjas) y la cancelada no ocupa', count($sinCitas) === 4 && $horas($sinCitas)[0] === '2026-11-02 08:00');

// C-03: si Citas no responde, el caso de uso no devuelve franjas (podrían estar ocupadas) y el error llega.
$error = null; $franjas = null;
try { $franjas = $consultar($base, new FuenteHospitalConCitasRemotas($base, function () { throw new ErrorPersistencia('Citas no responde'); })); } catch (ErrorPersistencia $e) { $error = $e; }
ver('C-03 Citas no responde → ErrorPersistencia y ninguna franja ofrecida', $error !== null && $franjas === null);

// C-04: el cambio no toca el código existente.
$src = array_merge(...array_map(fn ($c) => glob("$modulo/micro-his-medicos/src/$c/*.php"), ['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion']));
ver('C-04 src/ no menciona la fuente nueva (no hubo que editar nada existente)', array_filter($src, fn ($f) => str_contains((string) file_get_contents($f), 'FuenteHospitalConCitasRemotas')) === [] && count($src) >= 4);

// M-01..M-03: la matriz solo cita archivos y pruebas que existen y cubre los requisitos.
$doc = (string) file_get_contents(__DIR__ . '/semana-06-defensa-primer-parcial.md');
preg_match_all('/`((?:micro-his-medicos|semana-\d\d)\/[\w\/.\-]+\.\w+)`/', $doc, $m);
$citadas = array_values(array_unique($m[1]));
$faltan = array_values(array_filter($citadas, fn ($p) => !is_file("$modulo/$p")));
$ajenas = array_values(array_filter($faltan, fn ($p) => preg_match('#^semana-0[1-5]/#', $p)));   // semanas 1 a 5 que quizá aún no están en esta copia
$propias = array_diff($faltan, $ajenas);
ver('M-01 cada evidencia citada en la matriz existe (' . count($citadas) . ' rutas' . ($ajenas ? '; ' . count($ajenas) . ' de semanas anteriores no presentes en esta copia' : '') . ')', $propias === [] && count($citadas) >= 8, implode(', ', $propias));
preg_match_all('/\b(RF-\d\d|RNF-\d\d)\b/', $doc, $r); $cit = array_unique($r[1]);
ver('M-02 la matriz cubre RF-01..RF-07 y RNF-01..RNF-05', count(array_diff(array_merge(array_map(fn ($n) => sprintf('RF-%02d', $n), range(1, 7)), array_map(fn ($n) => sprintf('RNF-%02d', $n), range(1, 5))), $cit)) === 0);
$tests = (string) file_get_contents("$modulo/micro-his-medicos/tests/ejecutar.php");
preg_match_all('/\b(?:APP|DOM|PER|REP|MVC|ARQ|HTTP)-\d\d\b/', $doc, $t); $ids = array_unique($t[0]);
$sinPrueba = array_values(array_filter($ids, fn ($id) => !str_contains($tests, "'$id'")));
ver('M-03 cada prueba citada en la matriz existe en tests/ejecutar.php (' . count($ids) . ' ids)', $sinPrueba === [] && count($ids) >= 6, implode(', ', $sinPrueba));

echo $fallas === 0 ? "Resultado: el cambio se resuelve con una fuente nueva y sin editar código existente, y la matriz coincide con el repositorio\n" : "Resultado: $fallas FALLA(S)\n";
exit($fallas === 0 ? 0 : 1);
