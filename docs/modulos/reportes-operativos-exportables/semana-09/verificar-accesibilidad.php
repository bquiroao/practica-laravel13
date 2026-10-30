<?php
declare(strict_types=1);

// Semana 9 — Mide la accesibilidad y la usabilidad del diseño de la semana 8 (wireframes y documento).
//   CL-xx: puntos que se cumplen.  HAL-xx: hallazgos que se reproducen tal como los describe el informe.
//   PASA = la afirmación del informe es cierta. Cuando se corrija el diseño, el HAL correspondiente pasa a FALLA y el informe se actualiza.
// Uso desde la raíz del repo: php docs/modulos/reportes-operativos-exportables/semana-09/verificar-accesibilidad.php
use Reportes\Aplicacion\{ExportarReporte, Usuario};
use Reportes\Dominio\Csv;
use Reportes\Persistencia\EnMemoria;
use function Reportes\Dominio\catalogo;

$mod = dirname(__DIR__); $s8 = "$mod/semana-08"; $micro = "$mod/micro-his-reportes"; $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$html = (string) @file_get_contents("$s8/wireframes/wireframes.html"); $dis = (string) @file_get_contents("$s8/semana-08-diseno-ux.md");
$informe = (string) @file_get_contents(__DIR__ . '/semana-09-evaluacion-usabilidad-accesibilidad.md');
$servicio = (string) @file_get_contents("$micro/src/Aplicacion/ExportarReporte.php");
if ($html === '' || $dis === '' || $servicio === '') { echo "Falta el diseño de la semana 8 (semana-08/) o el código del Micro-HIS; esta evaluación los necesita.\n"; exit(1); }
require "$micro/bootstrap.php";
$css = (string) (preg_match('/<style>(.*?)<\/style>/s', $html, $m) ? $m[1] : ''); $cuerpo = preg_replace('/<style.*?<\/style>/s', '', $html);
$seccion = fn (string $id) => (string) (preg_match('/<section class="wf" id="' . $id . '">.*?<\/section>/s', $html, $x) ? $x[0] : '');

$color = function (string $sel, string $prop) use ($css): ?string {
    if (!preg_match('/(?<![\w.>])' . preg_quote($sel, '/') . '\{[^}]*?(?<![\w-])' . $prop . ':\s*(?:[^;}]*?\s)?(#[0-9a-f]{3,6})/i', $css, $m)) { return null; }
    return strlen($m[1]) === 4 ? '#' . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2] . $m[1][3] . $m[1][3] : strtolower($m[1]);
};
$luz = function (string $c): float { $v = array_map(fn ($h) => hexdec($h) / 255, str_split(substr($c, 1), 2)); $v = array_map(fn ($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $v); return 0.2126 * $v[0] + 0.7152 * $v[1] + 0.0722 * $v[2]; };
$contraste = function (string $a, string $b) use ($luz): float { [$x, $y] = [$luz($a), $luz($b)]; return (max($x, $y) + 0.05) / (min($x, $y) + 0.05); };
$fondo = $color('.p', 'background') ?? '#ffffff';
$pares = ['texto de ayuda' => [$color('.hint', 'color'), $fondo], 'mensaje de error' => [$color('.t', 'color'), $fondo], 'etiqueta del panel' => [$color('.p>b', 'color'), $fondo],
          'botón principal' => [$color('.pri', 'color'), $color('.pri', 'background')], 'opción elegida' => [$color('.sel', 'color'), $color('.sel', 'background')], 'botón desactivado' => [$color('.off', 'color'), $fondo]];
$razon = array_map(fn ($p) => $p[0] && $p[1] ? $contraste($p[0], $p[1]) : 0.0, $pares);
ver('CL-01', count($pares) === 6 && array_filter($razon, fn ($r) => $r < 4.5) === [], 'Contraste de texto ≥ 4.5:1 en los 6 pares principales (menor: ' . sprintf('%.1f', min($razon)) . ':1)');
$borde = $color('.in', 'border') ?? '';
ver('CL-02', $borde !== '' && $contraste($borde, $fondo) >= 3, sprintf('Borde de los campos %.1f:1 sobre el fondo (mínimo 3:1 para componentes de interfaz)', $borde ? $contraste($borde, $fondo) : 0));
$wf1 = $seccion('wf1'); $wf2 = $seccion('wf2'); $wf3 = $seccion('wf3'); $wf4 = $seccion('wf4'); $wf5 = $seccion('wf5');
ver('CL-03', str_contains($wf5, '✓ Coincide') && str_contains($wf5, '△ No coincide'), 'El resultado de la comprobación se dice con símbolo y texto («✓ Coincide», «△ No coincide»), no solo con color');
preg_match('/\.btn\{[^}]*padding:(\d+)px[^}]*\}/', $css, $p); preg_match('/\.btn\{[^}]*border:(\d+)px/', $css, $b); $alto = 14 * 1.35 + 2 * ($p[1] ?? 0) + 2 * ($b[1] ?? 0);
ver('CL-04', $alto >= 24, sprintf('Botones de %.0f px de alto, sobre el mínimo de 24 px de WCAG 2.2', $alto));
$posGuardar = strpos($servicio, 'guardarExportacion('); $posEntrega = strpos($servicio, "return ['id' => \$id, 'hash'");
ver('CL-05', str_contains($wf3, 'Confirme la exportación') && str_contains($wf4, 'No se entregó ningún archivo') && $posGuardar !== false && $posEntrega !== false && $posGuardar < $posEntrega, 'Hay paso de confirmación y el servidor registra la exportación antes de entregar el archivo (si falla, no se entrega nada)');

// Hallazgos
ver('HAL-01', preg_match_all('/teclado|\bTab\b|\bEsc\b|\bEnter\b|foco/i', $dis . $cuerpo) === 0, 'F-01: el diseño de la semana 8 no menciona teclado, Tab, Esc, Enter ni foco (0 menciones)');
ver('HAL-02', substr_count($wf3, 'btn pri">Exportar</span>') === 1 && str_contains($wf3, 'Este archivo incluirá nombre y documento') && !preg_match('/casilla|checkbox|entiendo|escriba .{0,12}EXPORTAR/i', $html . $dis), 'F-02: exportar un archivo con nombre y documento de pacientes usa el mismo botón «Exportar» y el mismo paso que uno sin datos personales');
ver('HAL-03', preg_match('/<div class="ban w"><i>\d<\/i><b>△ No coincide\.<\/b>[^<]*<\/div>/', $wf5) === 1 && str_contains($wf5, 'Avise a quien administra el sistema'), 'F-03: el aviso «No coincide» manda a avisar a quien administra, pero no trae ningún botón ni dato para hacerlo');
$mem = EnMemoria::demo(); $svc = new ExportarReporte($mem, $mem, catalogo(), ['csv' => new Csv()], fn () => '2026-10-25 12:00:00'); $jefa = new Usuario('usr-jefa', 'jefatura', 'hosp-1', true);
$a = $svc->ejecutar('censo', ['desde' => '2026-10-01', 'hasta' => '2026-10-25'], 'csv', $jefa); $c = $svc->ejecutar('censo', ['desde' => '2026-10-01', 'hasta' => '2026-10-25'], 'csv', $jefa);
ver('HAL-04', $a['id'] !== $c['id'] && $a['hash'] === $c['hash'] && !preg_match('/sin conexi|no respond|sin respuesta|tiempo de espera|se perdió/i', $dis . $cuerpo), 'F-04: dos exportaciones iguales reciben números distintos (' . $a['id'] . ', ' . $c['id'] . ') y el diseño no cubre que se pierda la respuesta tras registrar');
ver('HAL-05', !preg_match('/aria-live|role="(alert|status)"/', $html . $dis) && preg_match_all('/Cargando reporte…|Generando…|Comprobando…/', $dis . $cuerpo) >= 3, sprintf('F-05: %d mensajes de carga o progreso y ningún aria-live ni role de alerta o estado', preg_match_all('/Cargando reporte…|Generando…|Comprobando…/', $dis . $cuerpo)));
preg_match("/'exp-' \. bin2hex\(random_bytes\((\d+)\)\)/", (string) file_get_contents("$micro/src/Persistencia/EnMemoria.php"), $lg);
ver('HAL-06', !preg_match('/class="btn[^"]*">Copiar/i', $wf4) && str_contains($wf5, 'Número de exportación') && preg_match('/exp-[0-9a-f]{12}/', $wf4) === 1 && isset($lg[1]) && strlen($a['id']) === 4 + 2 * (int) $lg[1], sprintf('F-06: el número de exportación tiene %d caracteres y W4 no ofrece botón para copiarlo (W5 dice que se copia)', strlen($a['id'])));
ver('HAL-07', substr_count($wf1, 'chip sel') >= 2 && !str_contains($wf1, '✓') && !preg_match('/aria-pressed|aria-checked|role="radio/', $html), sprintf('F-07: %d opciones elegidas en W1 (reporte y servicio) se distinguen solo por relleno y negrita, sin símbolo ni rol de opción', substr_count($wf1, 'chip sel')));
ver('HAL-08', preg_match_all('/(?:Desde|Hasta) <span class="in(?: err)?">/', $wf1) >= 4 && !preg_match('/calendario|selector de fecha|type="date"/i', $html . $dis), sprintf('F-08: las fechas se escriben a mano en %d campos de texto de W1, sin selector de fecha', preg_match_all('/(?:Desde|Hasta) <span class="in(?: err)?">/', $wf1)));
ver('HAL-09', preg_match_all('/\*\*\*<\/td>/', $wf2) >= 4 && !preg_match('/aria-label|visualmente oculto|sin permiso/i', $wf2), sprintf('F-09: W2 muestra %d celdas con «***» para los datos personales y ninguna tiene texto que diga que el dato no está disponible', preg_match_all('/\*\*\*<\/td>/', $wf2)));
preg_match('/\.t\{[^}]*font-size:(\d+)px/', $css, $te); preg_match('/\.hint\{[^}]*font-size:(\d+)px/', $css, $hi); preg_match('/body\{[^}]*font:(\d+)px/', $css, $cu);
ver('HAL-10', isset($te[1], $hi[1], $cu[1]) && (int) $te[1] < (int) $cu[1] && (int) $hi[1] < (int) $cu[1], sprintf('F-10: los mensajes de error miden %s px, la ayuda %s px y el cuerpo %s px', $te[1] ?? '?', $hi[1] ?? '?', $cu[1] ?? '?'));

// El informe lista los mismos hallazgos
$enInforme = preg_match_all('/^\| F-\d{2} \| ([^|]+) \|/m', $informe, $m) ? array_unique(array_merge(...array_map(fn ($c) => preg_match_all('/HAL-\d{2}/', $c, $h) ? $h[0] : [], $m[1]))) : [];
preg_match_all('/ver\(\'(HAL-\d{2})\'/', (string) file_get_contents(__FILE__), $enScript);
preg_match_all('/^\| (?:H\d+|\d\.\d\.\d+) \| [^|]+ \| (Cumple|Parcial|No cumple) \|/m', $informe, $filasChk);
$cuentaChk = array_count_values($filasChk[1]);
$resumen = preg_match('/de (\d+) puntos, (\d+) cumplen, (\d+) cumplen en parte y (\d+) no cumplen/', $informe, $rs) ? array_map('intval', array_slice($rs, 1)) : [];
ver('DOC-01', $informe !== '' && array_diff($enScript[1], $enInforme) === [] && preg_match_all('/^\| F-\d{2} \|/m', $informe) >= 6, sprintf('El informe cubre los %d hallazgos del script y su backlog tiene al menos 6 filas F-xx', count($enScript[1])));
ver('DOC-02', $resumen !== [] && $resumen === [array_sum($cuentaChk), $cuentaChk['Cumple'] ?? 0, $cuentaChk['Parcial'] ?? 0, $cuentaChk['No cumple'] ?? 0], sprintf('El resumen del checklist (%s) coincide con el conteo de sus filas (%d/%d/%d/%d)', $resumen ? implode('/', $resumen) : 'no está', array_sum($cuentaChk), $cuentaChk['Cumple'] ?? 0, $cuentaChk['Parcial'] ?? 0, $cuentaChk['No cumple'] ?? 0));
echo $fallas === 0 ? "\nResultado: todo lo que afirma la evaluación se cumple.\n" : "\nResultado: $fallas afirmaciones no se cumplen.\n";
exit($fallas ? 1 : 0);
