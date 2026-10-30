<?php
declare(strict_types=1);

// Semana 6 – respuesta al cambio práctico y comprobación de la matriz:  php demostrar-cambio.php
// Cambio: «Auditoría quiere el registro de exportaciones en un archivo». Se resuelve con UN adaptador nuevo del Repository (semana 4):
// ExportarReporte, Archivo, Filtros y el formato quedan intactos.
use Reportes\Aplicacion\{ErrorPersistencia, ExportarReporte, RepositorioExportaciones, Usuario};
use Reportes\Dominio\{Csv};
use Reportes\Persistencia\EnMemoria;
use function Reportes\Dominio\catalogo;

$modulo = dirname(__DIR__);
require "$modulo/micro-his-reportes/bootstrap.php";

/** Propuesta (pasaría tal cual a src/Persistencia/): una exportación por línea JSON; el id sale del número de línea. */
final class ExportacionesEnArchivo implements RepositorioExportaciones {
    public function __construct(private string $ruta) {}

    public function guardarExportacion(array $e): string {
        $id = 'exp-' . (count($this->filas()) + 1);
        if (@file_put_contents($this->ruta, json_encode(['id' => $id] + $e) . "\n", FILE_APPEND | LOCK_EX) === false) { throw new ErrorPersistencia('No se pudo escribir el archivo de exportaciones'); }
        return $id;
    }

    public function exportacion(string $id, string $hospitalId): ?array {
        foreach ($this->filas() as $f) { if ($f['id'] === $id && $f['hospital_id'] === $hospitalId) { return array_diff_key($f, ['id' => 1]); } }
        return null;
    }

    private function filas(): array { return is_file($this->ruta) ? array_map(fn ($l) => json_decode($l, true), file($this->ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : []; }
}

$fallas = 0;
function ver(string $que, bool $ok, string $detalle = ''): void { global $fallas; $fallas += $ok ? 0 : 1; printf("%-6s %s%s\n", $ok ? 'PASA' : 'FALLA', $que, $ok || $detalle === '' ? '' : " → $detalle"); }

$fuente = EnMemoria::demo();
$jefa = new Usuario('usr-jefa', 'jefatura', 'hosp-1', true);
$otra = new Usuario('usr-otra', 'jefatura', 'hosp-2', true);
$rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-25'];
$caso = fn (RepositorioExportaciones $r) => new ExportarReporte($r, $fuente, catalogo(), ['csv' => new Csv()], fn () => '2026-10-25 12:00:00');
$ruta = tempnam(sys_get_temp_dir(), 'expo'); unlink($ruta);

// C-01: contrato del Repository: mismo archivo, huella y filas con memoria y con archivo; reproducir coincide.
$mem = new EnMemoria(); $arch = new ExportacionesEnArchivo($ruta);
$a = $caso($mem)->ejecutar('censo', $rango, 'csv', $jefa); $b = $caso($arch)->ejecutar('censo', $rango, 'csv', $jefa);
ver('C-01 mismo archivo, huella y filas con memoria y con archivo; el censo trae 2 filas', [$a['contenido'], $a['hash'], $a['filas']] === [$b['contenido'], $b['hash'], $b['filas']] && $b['filas'] === 2);

// C-02: una instancia nueva lee lo guardado: reproducir coincide; otro hospital no ve la exportación (aislamiento).
$b2 = $caso($arch)->ejecutar('egresos', $rango, 'csv', $jefa);   // una segunda exportación: los id deben ser distintos
$nueva = new ExportacionesEnArchivo($ruta);
ver('C-02 desde una instancia nueva las 2 exportaciones tienen id distinto y reproducen igual; otro hospital no las ve',
    $b['id'] !== $b2['id'] && $caso($nueva)->reproducir($b['id'], $jefa)['coincide'] === true && $caso($nueva)->reproducir($b2['id'], $jefa)['coincide'] === true
    && $nueva->exportacion($b['id'], 'hosp-2') === null);

// C-03: error de persistencia: archivo no escribible → ErrorPersistencia y no se entrega archivo (RF-07: registrar antes de entregar).
$entregado = null; $error = null;
try { $entregado = $caso(new ExportacionesEnArchivo(sys_get_temp_dir() . '/no-existe-carpeta/exp.jsonl'))->ejecutar('censo', $rango, 'csv', $jefa); } catch (ErrorPersistencia $e) { $error = $e; }
ver('C-03 archivo no escribible → ErrorPersistencia y ningún archivo entregado', $error !== null && $entregado === null);

// C-04: el cambio no toca el código existente.
$src = array_merge(...array_map(fn ($c) => glob("$modulo/micro-his-reportes/src/$c/*.php"), ['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion']));
ver('C-04 src/ no menciona el adaptador nuevo (no hubo que editar nada existente)', array_filter($src, fn ($f) => str_contains((string) file_get_contents($f), 'ExportacionesEnArchivo')) === [] && count($src) >= 8);
@unlink($ruta);

// M-01..M-03: la matriz solo cita archivos y pruebas que existen y cubre los requisitos.
$doc = (string) file_get_contents(__DIR__ . '/semana-06-defensa-primer-parcial.md');
preg_match_all('/`((?:micro-his-reportes|semana-\d\d)\/[\w\/.\-]+\.\w+)`/', $doc, $m);
$citadas = array_values(array_unique($m[1]));
$faltan = array_values(array_filter($citadas, fn ($p) => !is_file("$modulo/$p")));
$ajenas = array_values(array_filter($faltan, fn ($p) => preg_match('#^semana-0[1-5]/#', $p)));   // semanas 1 a 5 que quizá aún no están en esta copia
$propias = array_diff($faltan, $ajenas);
ver('M-01 cada evidencia citada en la matriz existe (' . count($citadas) . ' rutas' . ($ajenas ? '; ' . count($ajenas) . ' de semanas anteriores no presentes en esta copia' : '') . ')', $propias === [] && count($citadas) >= 8, implode(', ', $propias));
preg_match_all('/\b(RF-\d\d|RNF-\d\d)\b/', $doc, $r); $cit = array_unique($r[1]);
ver('M-02 la matriz cubre RF-01..RF-10 y RNF-01..RNF-06', count(array_diff(array_merge(array_map(fn ($n) => sprintf('RF-%02d', $n), range(1, 10)), array_map(fn ($n) => sprintf('RNF-%02d', $n), range(1, 6))), $cit)) === 0);
$tests = (string) file_get_contents("$modulo/micro-his-reportes/tests/ejecutar.php");
preg_match_all('/\b(?:APP|DOM|PER|REP|MVC|ARQ|HTTP)-\d\d\b/', $doc, $t); $ids = array_unique($t[0]);
$sinPrueba = array_values(array_filter($ids, fn ($id) => !str_contains($tests, "'$id'")));
ver('M-03 cada prueba citada en la matriz existe en tests/ejecutar.php (' . count($ids) . ' ids)', $sinPrueba === [] && count($ids) >= 6, implode(', ', $sinPrueba));

echo $fallas === 0 ? "Resultado: el cambio se resuelve con un adaptador nuevo y sin editar código existente, y la matriz coincide con el repositorio\n" : "Resultado: $fallas FALLA(S)\n";
exit($fallas === 0 ? 0 : 1);
