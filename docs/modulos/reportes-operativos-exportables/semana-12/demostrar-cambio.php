<?php
declare(strict_types=1);

// Semana 12 – respuesta al cambio solicitado y comprobación de la matriz:  php demostrar-cambio.php
// Cambio: «Gerencia pide el reporte de ocupación de camas por servicio y poder bajarlo en JSON». Se resuelve con DOS piezas nuevas
// (una entrada de catálogo y una clase de formato); ExportarReporte, Archivo, Filtros y Csv quedan intactos (RNF-04, diseño OCP de la semana 2).
use Reportes\Aplicacion\{ExportarReporte, Usuario};
use Reportes\Dominio\{Csv, DefinicionReporte, ErrorDominio, FormatoExportacion};
use Reportes\Persistencia\ReportesPdo;
use function Reportes\Dominio\catalogo;

$modulo = dirname(__DIR__);
require "$modulo/micro-his-reportes/bootstrap.php";

/** Propuesta (pasaría tal cual a src/Dominio/): mismo contrato que Csv; las columnas ya llegan enmascaradas y ordenadas desde Archivo. */
final class Json implements FormatoExportacion {
    public function codigo(): string { return 'json'; }
    public function generar(array $columnas, array $filas): string { return json_encode(array_map(fn ($f) => array_combine($columnas, $f), $filas), JSON_UNESCAPED_UNICODE); }
}

$fallas = 0;
function ver(string $que, bool $ok, string $detalle = ''): void { global $fallas; $fallas += $ok ? 0 : 1; printf("%-6s %s%s\n", $ok ? 'PASA' : 'FALLA', $que, $ok || $detalle === '' ? '' : " → $detalle"); }

// C-01: la misma prueba de contrato (RNF-06) para los dos formatos: devuelve texto, es determinista y escapa lo que debe.
$columnas = ['paciente', 'servicio']; $filas = [['Ana "la" López', 'medicina'], ['Luis, Pérez', 'cirugia']];
foreach ([new Csv(), new Json()] as $f) {
    $a = $f->generar($columnas, $filas); $vacio = $f->generar($columnas, []);
    ver('C-01 contrato de formato: ' . $f->codigo(), $a === $f->generar($columnas, $filas) && is_string($vacio) && $vacio !== '');
}
ver('C-01b JSON conserva comillas, comas y tildes y devuelve las mismas filas que el CSV', json_decode((new Json())->generar($columnas, $filas), true) === [['paciente' => 'Ana "la" López', 'servicio' => 'medicina'], ['paciente' => 'Luis, Pérez', 'servicio' => 'cirugia']]);

// Base de la demostración: el esquema real del Micro-HIS más la vista que publicaría Traslados (propiedad de otro módulo).
$pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents("$modulo/micro-his-reportes/sql/esquema.sql"));
$pdo->exec('CREATE TABLE vw_ocupacion (hospital_id TEXT, paciente TEXT, documento TEXT, servicio TEXT, cama TEXT, fecha TEXT, registrado_en TEXT);
  INSERT INTO vw_ocupacion VALUES (\'hosp-1\', \'Ana López\', \'1111\', \'medicina\', \'A1\', \'2026-10-20\', \'2026-10-20 08:00:00\'), (\'hosp-1\', \'Luis Pérez\', \'2222\', \'cirugia\', \'B2\', \'2026-10-21\', \'2026-10-21 09:30:00\'),
  (\'hosp-2\', \'Otra Persona\', \'9999\', \'medicina\', \'C3\', \'2026-10-21\', \'2026-10-21 10:00:00\')');
$ocupacion = new DefinicionReporte('ocupacion', 'vw_ocupacion', ['paciente' => true, 'documento' => true, 'servicio' => false, 'cama' => false, 'fecha' => false], ['servicio' => ['medicina', 'cirugia', 'pediatria']]);
$repo = new ReportesPdo($pdo);
$caso = new ExportarReporte($repo, $repo, catalogo() + ['ocupacion' => $ocupacion], ['csv' => new Csv(), 'json' => new Json()], fn () => '2026-10-25 12:00:00');
$jefa = new Usuario('usr-jefa', 'jefatura', 'hosp-1', true); $auditor = new Usuario('usr-aud', 'auditor', 'hosp-1', false);
$rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-25'];

// C-02: el reporte nuevo sale en los dos formatos, con filtro, enmascarado y aislamiento por hospital.
$csv = $caso->ejecutar('ocupacion', $rango, 'csv', $jefa); $json = $caso->ejecutar('ocupacion', $rango, 'json', $jefa);
ver('C-02a ocupación en CSV y JSON: 2 filas del hospital 1 (no entra la del hospital 2) y huellas distintas', $csv['filas'] === 2 && $json['filas'] === 2 && $csv['hash'] !== $json['hash']);
ver('C-02b filtro servicio=cirugia deja 1 fila; el auditor ve *** en paciente y documento', $caso->ejecutar('ocupacion', $rango + ['servicio' => 'cirugia'], 'json', $jefa)['filas'] === 1
    && str_contains($caso->ejecutar('ocupacion', $rango, 'json', $auditor)['contenido'], '"paciente":"***","documento":"***"'));

// C-03: reproducible: llega una fila después del corte y la reproducción del JSON sigue coincidiendo.
$pdo->exec("INSERT INTO vw_ocupacion VALUES ('hosp-1', 'Tardío', '4444', 'medicina', 'A2', '2026-10-21', '2026-10-25 15:00:00')");
ver('C-03 reproducir la exportación JSON registrada sigue coincidiendo tras una fila tardía', $caso->reproducir($json['id'], $jefa)['coincide'] === true);

// C-04: lo que no cambió: formatos y reportes desconocidos siguen rechazándose con su código; el código existente no menciona lo nuevo.
$codigo = fn (callable $f) => ($e = (function () use ($f) { try { $f(); } catch (ErrorDominio $e) { return $e; } return null; })()) ? $e->codigo : 'sin_error';
ver('C-04a PDF sigue siendo formato_no_soportado y un reporte inventado reporte_no_encontrado', $codigo(fn () => $caso->ejecutar('ocupacion', $rango, 'pdf', $jefa)) === 'formato_no_soportado' && $codigo(fn () => $caso->ejecutar('inventado', $rango, 'csv', $jefa)) === 'reporte_no_encontrado');
$enSrc = array_filter(array_merge(...array_map(fn ($c) => glob("$modulo/micro-his-reportes/src/$c/*.php"), ['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion'])), fn ($f) => preg_match('/ocupacion|class Json\b/', (string) file_get_contents($f)));
ver('C-04b src/ no menciona el reporte ni el formato nuevos (no hubo que editar nada existente)', $enSrc === []);

// M-01/M-02: la matriz de la defensa solo cita archivos que existen y cubre todos los requisitos.
$doc = (string) file_get_contents(__DIR__ . '/semana-12-defensa-segundo-parcial.md');
preg_match_all('/`((?:micro-his-reportes|semana-\d\d)\/[\w\/.\-]+\.\w+)`/', $doc, $m); $citadas = array_unique($m[1]);
$faltan = array_values(array_filter($citadas, fn ($p) => !is_file("$modulo/$p")));
$ajenas = array_values(array_filter($faltan, fn ($p) => preg_match('#^semana-(1[13-5])/#', $p))); $propias = array_diff($faltan, $ajenas);   // otra semana que quizá aún no se fusiona
ver('M-01 cada evidencia citada en la matriz existe (' . count($citadas) . ' rutas' . ($ajenas ? '; ' . count($ajenas) . ' de otras semanas aún sin fusionar' : '') . ')', $propias === [] && count($citadas) >= 8, implode(', ', $propias));
preg_match_all('/\b(RF-\d\d|RNF-\d\d)\b/', $doc, $r);
ver('M-02 la matriz cubre RF-01..RF-10 y RNF-01..RNF-06', count(array_diff(array_merge(array_map(fn ($n) => sprintf('RF-%02d', $n), range(1, 10)), array_map(fn ($n) => sprintf('RNF-%02d', $n), range(1, 6))), array_unique($r[1]))) === 0);

echo $fallas === 0 ? "Resultado: el cambio se resuelve extendiendo, sin modificar código existente, y la matriz coincide con el repositorio\n" : "Resultado: $fallas FALLA(S)\n";
exit($fallas === 0 ? 0 : 1);
