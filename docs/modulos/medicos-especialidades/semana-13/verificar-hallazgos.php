<?php
declare(strict_types=1);

// Reproduce los hallazgos RT-01 a RT-06 de la revisión técnica (semana 13) contra el Micro-HIS de Médicos.
//   php semana-13/verificar-hallazgos.php [carpeta-del-micro-his]
// PRESENTE = el defecto se reproduce (estado del código revisado). AUSENTE = ya está corregido.
// Código de salida: 0 si los 6 siguen presentes; 1 si alguno ya no se reproduce (úselo tras corregir).
use Medicos\Aplicacion\{AsociarEspecialidad, ConsultarDisponibilidad, RegistrarMedico, Usuario};
use Medicos\Persistencia\MedicosPdo;
use Medicos\Presentacion\MedicosControlador;

$raiz = realpath($argv[1] ?? __DIR__ . '/../micro-his-medicos') ?: exit("No encuentro el Micro-HIS\n");
require $raiz . '/bootstrap.php';
$avisos = [];
set_error_handler(function (int $n, string $m) { $GLOBALS['avisos'][] = $m; return true; });   // captura los Warnings sin imprimirlos
$nuevo = interface_exists('Medicos\Aplicacion\RepositorioMedicos');   // true desde la semana 4 (Repository)
function armar(string $raiz, bool $nuevo, ?Closure $ids = null): array {
    $pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents($raiz . '/sql/esquema.sql'));
    $r = new MedicosPdo($pdo); $n = 0; $ids ??= function () use (&$n) { return 'id-' . ++$n; }; $hoy = fn () => '2026-10-28';
    $c = $nuevo ? [new RegistrarMedico($r, $r, $hoy, $ids), new AsociarEspecialidad($r, $r, $ids), new ConsultarDisponibilidad($r, $r, $hoy)]
                : [new RegistrarMedico($r, $hoy, $ids), new AsociarEspecialidad($r, $ids), new ConsultarDisponibilidad($r, $hoy)];
    $usuarios = ['adm' => new Usuario('a', 'admin'), 'rec' => new Usuario('r', 'recepcion')]; $ctl = new MedicosControlador($c[0], $c[1], $c[2], $usuarios);
    // Misma llamada para las dos formas del controlador: atender() en la semana 3, Enrutador + Peticion desde la semana 4.
    $api = $nuevo ? new class(new \Medicos\Presentacion\Enrutador($ctl)) { function __construct(private $e) {}
                      function atender($m, $r, $u, $c = '', $q = []) { return $this->e->atender(new \Medicos\Presentacion\Peticion($m, $r, $q, $u, $c)); } }
                  : $ctl;
    return [$pdo, $api];
}
$resultados = [];
function hallazgo(string $id, string $que, callable $f): void {
    try { [$presente, $dato] = $f(); } catch (\Throwable $e) { [$presente, $dato] = [true, get_class($e) . ' sin capturar']; }
    $GLOBALS['resultados'][$id] = $presente;
    printf("%-5s %-9s %s → %s\n", $id, $presente ? 'PRESENTE' : 'AUSENTE', $que, $dato);
}
$consulta = ['especialidad' => 'MI', 'desde' => '2026-11-02', 'hasta' => '2026-11-02'];

hallazgo('RT-01', 'franja que termina después del horario (08:00–09:45)', function () use ($raiz, $nuevo, $consulta) {
    [$pdo, $api] = armar($raiz, $nuevo); $pdo->exec("UPDATE horarios_medico SET fin = '09:45' WHERE doctor_profile_id = 'med-ana'");
    $f = array_column($api->atender('GET', '/api/v1/disponibilidad', 'rec', '', $consulta)[1]['franjas'], 'inicio');
    return [in_array('2026-11-02 09:30', $f, true), 'última franja ofrecida: ' . end($f)];
});
hallazgo('RT-02', 'médico con perfil vencido (valid_to pasado) sigue con franjas', function () use ($raiz, $nuevo, $consulta) {
    [$pdo, $api] = armar($raiz, $nuevo); $pdo->exec("UPDATE doctor_profiles SET valid_to = '2026-06-01' WHERE doctor_profile_id = 'med-ana'");
    $n = count($api->atender('GET', '/api/v1/disponibilidad', 'rec', '', $consulta)[1]['franjas']);
    return [$n > 0, "$n franjas de un perfil que terminó el 2026-06-01"];
});
hallazgo('RT-03', 'campo «hasta» que no es texto rompe la petición', function () use ($raiz, $nuevo) {
    [, $api] = armar($raiz, $nuevo);
    try { $r = $api->atender('POST', '/api/v1/medicos/med-ana/especialidades', 'adm', '{"especialidad_id":"esp-ped","desde":"2026-11-01","hasta":5}'); }
    catch (\TypeError) { return [true, 'TypeError sin capturar (PHP responde 500 sin JSON)']; }
    return [false, 'respuesta ' . $r[0] . ' ' . $r[1]['codigo']];
});
hallazgo('RT-04', 'colegiado repetido por una petición simultánea da 500 en vez de 409', function () use ($raiz, $nuevo) {
    $pdoRef = null;
    $ids = function () use (&$pdoRef) { static $i = 0; if ($i++ === 0 && $pdoRef) { $pdoRef->exec("INSERT INTO doctor_profiles VALUES ('med-otro', 'u-ana', '33333', 'inactive', '2026-01-01', NULL)"); } return 'id-' . $i; };
    [$pdo, $api] = armar($raiz, $nuevo, $ids); $pdoRef = $pdo;   // el otro registro entra entre la verificación y el guardado
    $r = $api->atender('POST', '/api/v1/medicos', 'adm', '{"usuario_id":"u-luis","colegiado":"33333"}');
    return [$r[0] === 500, 'respuesta ' . $r[0] . ' ' . $r[1]['codigo']];
});
hallazgo('RT-05', 'código de especialidad que no existe devuelve 200 sin franjas', function () use ($raiz, $nuevo, $consulta) {
    [, $api] = armar($raiz, $nuevo); $r = $api->atender('GET', '/api/v1/disponibilidad', 'rec', '', ['especialidad' => 'ZZ'] + $consulta);
    return [$r[0] === 200 && $r[1]['franjas'] === [], 'respuesta ' . $r[0] . ' con ' . count($r[1]['franjas'] ?? []) . ' franjas'];
});
hallazgo('RT-06', 'usuario_id que no es texto genera un Warning de PHP', function () use ($raiz, $nuevo) {
    [, $api] = armar($raiz, $nuevo); $GLOBALS['avisos'] = [];
    $r = $api->atender('POST', '/api/v1/medicos', 'adm', '{"usuario_id":["x"],"colegiado":"3"}');
    return [$GLOBALS['avisos'] !== [], ($GLOBALS['avisos'][0] ?? 'sin aviso') . '; respuesta ' . $r[0]];
});
$presentes = count(array_filter($resultados));
printf("\nPresentes: %d  Ausentes: %d\n", $presentes, count($resultados) - $presentes);
exit($presentes === count($resultados) ? 0 : 1);
