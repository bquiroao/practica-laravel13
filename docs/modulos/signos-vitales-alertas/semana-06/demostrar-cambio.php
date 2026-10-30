<?php
declare(strict_types=1);

// Semana 6 – respuesta al cambio práctico y comprobación de la matriz:  php demostrar-cambio.php
// Cambio: «las tomas deben guardarse en un archivo porque la base del hospital estará en mantenimiento». Se resuelve con UN adaptador
// nuevo del Repository (semana 4): RegistrarToma, ConsultarTomas y el Dominio quedan intactos.
use SignosVitales\Aplicacion\{ConsultarTomas, ErrorPersistencia, RegistrarToma, RepositorioTomas, Usuario};
use SignosVitales\Dominio\{Clasificacion, Medicion, RangoSigno, Toma, TipoSigno};
use SignosVitales\Persistencia\EnMemoria;

$modulo = dirname(__DIR__);
require "$modulo/micro-his-signos-vitales/bootstrap.php";

/** Propuesta (pasaría tal cual a src/Persistencia/): una toma por línea en un archivo JSON; guarda todo o nada con una sola escritura. */
final class TomasEnArchivo implements RepositorioTomas {
    public function __construct(private string $ruta) {}

    public function guardar(Toma $toma): void {
        $mediciones = [];
        foreach ($toma->mediciones as $m) { $mediciones[$m->tipo->value] = ['valor' => $m->valor, 'clasificacion' => $toma->clasificaciones[$m->tipo->value]->value]; }
        $linea = json_encode(['toma_id' => $toma->id, 'episodio_id' => $toma->episodioId, 'tomada_en' => $toma->tomadaEn->format('c'), 'autor_id' => $toma->autorId, 'mediciones' => $mediciones], JSON_PRESERVE_ZERO_FRACTION) . "\n";
        if (@file_put_contents($this->ruta, $linea, FILE_APPEND | LOCK_EX) === false) { throw new ErrorPersistencia('No se pudo escribir el archivo de tomas'); }
    }

    public function deEpisodio(string $episodioId): array {
        $filas = is_file($this->ruta) ? array_map(fn ($l) => json_decode($l, true), file($this->ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : [];
        $propias = array_values(array_filter($filas, fn ($f) => $f['episodio_id'] === $episodioId));
        usort($propias, fn ($a, $b) => [$b['tomada_en'], $a['toma_id']] <=> [$a['tomada_en'], $b['toma_id']]);
        return array_map(fn ($f) => array_diff_key($f, ['episodio_id' => 1]), $propias);
    }
}

$fallas = 0;
function ver(string $que, bool $ok, string $detalle = ''): void { global $fallas; $fallas += $ok ? 0 : 1; printf("%-6s %s%s\n", $ok ? 'PASA' : 'FALLA', $que, $ok || $detalle === '' ? '' : " → $detalle"); }

$clinica = fn () => new EnMemoria(['epi-1' => ['paciente_id' => 'pac-adulto', 'hospital_id' => 'hosp-1', 'fecha_nacimiento' => '1980-05-10', 'activo' => true]],
    [[18, 120, ['fc' => new RangoSigno(60, 100, 40, 130), 'spo2' => new RangoSigno(95, 100, 90, 100)]]]);
$enf = new Usuario('enf-ana', 'enfermeria', 'hosp-1');
$entrada = ['paciente_id' => 'pac-adulto', 'episodio_id' => 'epi-1', 'mediciones' => ['fc' => 135, 'spo2' => 97]];
$escenario = function (RepositorioTomas $repo) use ($clinica, $enf, $entrada) {   // el mismo guion para cualquier adaptador; la segunda toma es una hora después
    $c = $clinica(); $n = 0;
    $reloj = function () use (&$n) { return (new DateTimeImmutable('2026-10-25 08:00'))->modify('+' . $n . ' hours'); };
    $registrar = new RegistrarToma($repo, $c, $reloj, function () use (&$n) { return 'toma-' . ++$n; });
    $a = $registrar->ejecutar($entrada, $enf); $b = $registrar->ejecutar(['mediciones' => ['fc' => 80]] + $entrada, $enf);
    return [$a, $b, (new ConsultarTomas($repo, $c))->ejecutar('pac-adulto', 'epi-1', $enf)];
};
$ruta = tempnam(sys_get_temp_dir(), 'tomas'); unlink($ruta);

// C-01: contrato del Repository: el adaptador nuevo da las mismas respuestas y la misma consulta que el de memoria.
$enMemoria = $escenario(new EnMemoria()); $enArchivo = $escenario(new TomasEnArchivo($ruta));
ver('C-01 RegistrarToma y ConsultarTomas dan lo mismo con memoria y con archivo (2 tomas, la más reciente primero, FC 135 crítica)', $enMemoria === $enArchivo && array_column($enArchivo[2], 'toma_id') === ['toma-2', 'toma-1'] && ($enArchivo[0]['alertas'][0]['nivel'] ?? '') === 'critica');

// C-02: sobrevive al reinicio: una instancia nueva lee lo que escribió la anterior (lo que la memoria no hace).
ver('C-02 una instancia nueva del adaptador lee las 2 tomas guardadas antes', count((new TomasEnArchivo($ruta))->deEpisodio('epi-1')) === 2);

// C-03: error de persistencia: si el archivo no se puede escribir, el caso de uso recibe ErrorPersistencia y no devuelve resultado (misma regla que PER-01/PER-02).
$error = null;
try { (new RegistrarToma(new TomasEnArchivo(sys_get_temp_dir() . '/no-existe-carpeta/tomas.jsonl'), $clinica(), fn () => new DateTimeImmutable('2026-10-25 08:00')))->ejecutar($entrada, $enf); } catch (ErrorPersistencia $e) { $error = $e; }
ver('C-03 archivo no escribible → ErrorPersistencia', $error !== null);

// C-04: el cambio no toca el código existente: la clase nueva no está en src/ y ningún caso de uso conoce el archivo.
$src = array_merge(...array_map(fn ($c) => glob("$modulo/micro-his-signos-vitales/src/$c/*.php"), ['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion']));
$menciona = array_filter($src, fn ($f) => str_contains((string) file_get_contents($f), 'TomasEnArchivo'));
ver('C-04 src/ no menciona el adaptador nuevo (no hubo que editar nada existente)', $menciona === [] && count($src) >= 10);
@unlink($ruta);

// M-01: la matriz de la defensa solo cita archivos que existen.
$doc = (string) file_get_contents(__DIR__ . '/semana-06-defensa-primer-parcial.md');
preg_match_all('/`((?:micro-his-signos-vitales|semana-\d\d)\/[\w\/.\-]+\.\w+)`/', $doc, $m);
$citadas = array_values(array_unique($m[1]));
$faltan = array_values(array_filter($citadas, fn ($p) => !is_file("$modulo/$p")));
$ajenas = array_values(array_filter($faltan, fn ($p) => preg_match('#^semana-0[1-5]/#', $p)));   // semanas 1 a 5 que quizá aún no están en esta copia
$propias = array_diff($faltan, $ajenas);
ver('M-01 cada evidencia citada en la matriz existe (' . count($citadas) . ' rutas' . ($ajenas ? '; ' . count($ajenas) . ' de semanas anteriores no presentes en esta copia' : '') . ')', $propias === [] && count($citadas) >= 8, implode(', ', $propias));
preg_match_all('/\b(RF-\d\d|RNF-\d\d)\b/', $doc, $r); $cit = array_unique($r[1]);
ver('M-02 la matriz cubre RF-01..RF-10 y RNF-01, 02, 04, 05, 06', count(array_diff(array_merge(array_map(fn ($n) => sprintf('RF-%02d', $n), range(1, 10)), ['RNF-01', 'RNF-02', 'RNF-04', 'RNF-05', 'RNF-06']), $cit)) === 0);
$tests = (string) file_get_contents("$modulo/micro-his-signos-vitales/tests/ejecutar.php");
preg_match_all('/\b(?:APP|DOM|PER|REP|MVC|ARQ|HTTP|RES|CON)-\d\d\b/', $doc, $t); $ids = array_unique($t[0]);
$sinPrueba = array_values(array_filter($ids, fn ($id) => !str_contains($tests, "'$id'")));
ver('M-03 cada prueba citada en la matriz existe en tests/ejecutar.php (' . count($ids) . ' ids)', $sinPrueba === [] && count($ids) >= 6, implode(', ', $sinPrueba));

echo $fallas === 0 ? "Resultado: el cambio se resuelve con un adaptador nuevo y sin editar código existente, y la matriz coincide con el repositorio\n" : "Resultado: $fallas FALLA(S)\n";
exit($fallas === 0 ? 0 : 1);
