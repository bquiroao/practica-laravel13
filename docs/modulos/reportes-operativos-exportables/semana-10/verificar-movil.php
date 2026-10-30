<?php
declare(strict_types=1);

// Semana 10 — Comprueba que el diseño móvil es coherente con el diseño de la semana 8 y con el Micro-HIS, y cumple sus propias reglas.
// Uso desde la raíz del repo: php docs/modulos/reportes-operativos-exportables/semana-10/verificar-movil.php
use Reportes\Aplicacion\{ExportarReporte, Usuario};
use Reportes\Dominio\Csv;
use Reportes\Persistencia\EnMemoria;
use function Reportes\Dominio\catalogo;

$base = dirname(__DIR__); $micro = "$base/micro-his-reportes"; $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$leer = fn (string $r) => (string) @file_get_contents($r);
$html = $leer(__DIR__ . '/wireframes-movil/moviles.html'); $doc = $leer(__DIR__ . '/semana-10-diseno-movil.md'); $s8 = $leer("$base/semana-08/semana-08-diseno-ux.md");
$servicio = $leer("$micro/src/Aplicacion/ExportarReporte.php");
if ($html === '' || $doc === '' || $s8 === '' || $servicio === '') { echo "Faltan los wireframes móviles, este documento, el diseño de la semana 8 o el código del Micro-HIS.\n"; exit(1); }
require "$micro/bootstrap.php";
$css = (string) (preg_match('/<style>(.*?)<\/style>/s', $html, $m) ? $m[1] : '');
$seccion = fn (string $id) => (string) (preg_match('/<section class="wf" id="' . $id . '">.*?<\/section>/s', $html, $x) ? $x[0] : '');

preg_match_all('/<section class="wf" id="(m\d)"/', $html, $pant); preg_match('/\.tel\{width:(\d+)px/', $css, $w);
ver('MOV-01', count($pant[1]) >= 3 && count($pant[1]) <= 5 && isset($w[1]) && $w[1] >= 320 && $w[1] <= 430, sprintf('%d pantallas móviles (3 a 5) dibujadas a %s px (rango 320 a 430)', count($pant[1]), $w[1] ?? '?'));
preg_match('/\.btn\{[^}]*min-height:(\d+)px/', $css, $b); preg_match('/\.campo\{[^}]*height:(\d+)px/', $css, $c); preg_match('/\.chip\{[^}]*min-height:(\d+)px/', $css, $o);
ver('MOV-02', isset($b[1], $c[1], $o[1]) && min($b[1], $c[1], $o[1]) >= 44, sprintf('Botones de %s px, campos de %s px y opciones de %s px de alto (mínimo 44 px)', $b[1] ?? '?', $c[1] ?? '?', $o[1] ?? '?'));
preg_match_all('/font(?:-size)?:\s*(?:bold\s+)?(\d+)px/', $css, $f); preg_match('/\.campo\{[^}]*font-size:(\d+)px/', $css, $fc);
ver('MOV-03', $f[1] !== [] && min($f[1]) >= 14 && ($fc[1] ?? 0) >= 16, sprintf('Letra mínima de %d px (mínimo 14) y de %s px en los campos (mínimo 16)', $f[1] ? min($f[1]) : 0, $fc[1] ?? '?'));

// Los reportes y los servicios de M1 son los del catálogo del servidor
$cat = catalogo(); $m1 = $seccion('m1');
preg_match_all('/data-reporte="(\w+)"/', $m1, $rp); preg_match_all('/data-valor="(\w+)"/', $m1, $vl);
$valores = array_values(array_diff($vl[1], ['todos'])); $reales = $cat['censo']->filtros['servicio'] ?? []; $a = $rp[1]; $d = array_keys($cat); sort($a); sort($d); sort($valores); sort($reales);
ver('MOV-04', $d !== [] && $a === $d && $reales !== [] && $valores === $reales && substr_count($m1, 'data-tipo="fecha"') === 2, sprintf('M1 ofrece los %d reportes y los %d servicios del catálogo, más las dos fechas con selector (distintos: %s)', count($d), count($reales), implode(', ', array_merge(array_diff($a, $d), array_diff($d, $a), array_diff($valores, $reales), array_diff($reales, $valores))) ?: 'ninguno'));

// Los 9 códigos de error de la semana 8 tienen tratamiento móvil
preg_match_all('/^\| `(\w+)` \| \d{3} \| .{10,} \| .+ \|$/m', (string) strstr($s8, '## 4.'), $c8); preg_match_all('/^\| `(\w+)` \| \d{3} \| .{10,} \| M\d \|$/m', $doc, $cm);
$a = array_unique($c8[1]); $d = array_unique($cm[1]); sort($a); sort($d);
ver('MOV-05', count($a) === 9 && $a === $d, sprintf('Los %d códigos de error de la semana 8 tienen tratamiento en móvil (distintos: %s)', count($a), implode(', ', array_merge(array_diff($a, $d), array_diff($d, $a))) ?: 'ninguno'));
$m3 = $seccion('m3'); $m2 = $seccion('m2');
ver('MOV-06', substr_count($m3, 'btn pri">Exportar</div>') === 2 && str_contains($m3, 'ban w') && str_contains($m3, 'nombre y documento de pacientes') && str_contains($m3, 'Se guardará en su teléfono') && str_contains($m2, 'No disponible por su permiso') && str_contains(file_get_contents("$micro/src/Dominio/Reportes.php"), "'***'"), 'M3 distingue la exportación con datos personales (borde punteado y aviso de que queda en el teléfono) y M2 dice con palabras lo que el servidor enmascara con ***');
ver('MOV-07', preg_match('/\| 320 a 479 px \|.*\n\| 480 a 767 px \|.*\n\| 768 px o más \|.*semana 8/', $doc) === 1, 'Hay tres rangos de ancho (320, 480 y 768 px) y el último es el diseño de escritorio de la semana 8');
ver('MOV-08', preg_match('/## \d+\. Escenario 1.*?Decisión de contenido.*?Decisión de error.*?## \d+\. Escenario 2.*?Decisión de contenido.*?Decisión de error/s', $doc) === 1, 'Los dos escenarios móviles tienen decisión de contenido y decisión de error');
ver('MOV-09', !preg_match('/localStorage|sessionStorage|cookie/i', $html) && str_contains($html, 'No cierre esta pantalla') && str_contains($doc, 'no se guardan en el almacenamiento del navegador'), 'Los datos no se guardan en el navegador y M5 avisa que no se cierre la pantalla');
$png = array_filter($pant[1], fn ($p) => is_file(__DIR__ . "/wireframes-movil/semana-10-$p.png"));
ver('MOV-10', count($pant[1]) > 0 && count($png) === count($pant[1]), sprintf('Existen las %d imágenes de las pantallas', count($png)));

// M5 avisa que exportar de nuevo crea otro número: se comprueba contra el servicio
$mem = EnMemoria::demo(); $svc = new ExportarReporte($mem, $mem, catalogo(), ['csv' => new Csv()], fn () => '2026-10-25 12:00:00'); $jefa = new Usuario('usr-jefa', 'jefatura', 'hosp-1', true);
$x = $svc->ejecutar('censo', ['desde' => '2026-10-01', 'hasta' => '2026-10-25'], 'csv', $jefa); $y = $svc->ejecutar('censo', ['desde' => '2026-10-01', 'hasta' => '2026-10-25'], 'csv', $jefa);
$pg = strpos($servicio, 'guardarExportacion('); $pe = strpos($servicio, "return ['id' => \$id, 'hash'");
ver('MOV-11', $x['id'] !== $y['id'] && $x['hash'] === $y['hash'] && $pg !== false && $pe !== false && $pg < $pe && str_contains($seccion('m5'), 'se creará otro número'), sprintf('Dos exportaciones iguales reciben números distintos (%s, %s) con el mismo sello, y se registran antes de entregar; M5 lo avisa', $x['id'], $y['id']));
echo $fallas === 0 ? "\nResultado: el diseño móvil cumple lo que afirma.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
