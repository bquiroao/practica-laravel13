<?php
declare(strict_types=1);

// Semana 9 — Mide la accesibilidad y la usabilidad del diseño de la semana 8 (wireframes y documento).
//   CL-xx: puntos que se cumplen.  HAL-xx: hallazgos que se reproducen tal como los describe el informe.
//   PASA = la afirmación del informe es cierta. Cuando se corrija el diseño, el HAL correspondiente pasa a FALLA y el informe se actualiza.
// Uso desde la raíz del repo: php docs/modulos/traslados-altas-camas/semana-09/verificar-accesibilidad.php
$s8 = dirname(__DIR__) . '/semana-08'; $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$html = (string) @file_get_contents("$s8/wireframes/wireframes.html"); $dis = (string) @file_get_contents("$s8/semana-08-diseno-ux.md");
$informe = (string) @file_get_contents(__DIR__ . '/semana-09-evaluacion-usabilidad-accesibilidad.md');
if ($html === '' || $dis === '') { echo "No está el diseño de la semana 8 (semana-08/); esta evaluación lo necesita.\n"; exit(1); }
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
$borde = $color('.in', 'border') ?? ''; $bc = $color('.chip', 'border');
ver('CL-02', $borde !== '' && $contraste($borde, $fondo) >= 3, sprintf('Borde de los campos %.1f:1 sobre el fondo (mínimo 3:1 para componentes de interfaz)', $borde ? $contraste($borde, $fondo) : 0));
$wf3 = $seccion('wf3');
ver('CL-03', preg_match_all('/>(Disponible|Ocupada|En mantenimiento)</', $wf3) >= 3, 'El estado de cada cama se dice con texto (Disponible, Ocupada, En mantenimiento), no solo con color');
preg_match('/\.btn\{[^}]*padding:(\d+)px[^}]*\}/', $css, $p); preg_match('/\.btn\{[^}]*border:(\d+)px/', $css, $b); $alto = 14 * 1.35 + 2 * ($p[1] ?? 0) + 2 * ($b[1] ?? 0);
ver('CL-04', $alto >= 24, sprintf('Botones de %.0f px de alto, sobre el mínimo de 24 px de WCAG 2.2', $alto));
ver('CL-05', str_contains($html, 'Confirmar traslado') && str_contains($html, 'Confirmar alta') && str_contains($html, 'no se puede deshacer'), 'Traslado y alta tienen paso de confirmación y se avisa que no se deshace');

// Hallazgos
ver('HAL-01', preg_match_all('/teclado|\bTab\b|\bEsc\b|\bEnter\b|foco/i', $dis . $cuerpo) === 0, 'F-01: el diseño de la semana 8 no menciona teclado, Tab, Esc, Enter ni foco (0 menciones)');
ver('HAL-02', preg_match('/btn pri">Elegir<\/span>/', $wf3) === 1 && !str_contains($html, 'aria-label'), 'F-02: el botón de cada cama dice solo «Elegir», sin la cama, y no hay ningún aria-label');
ver('HAL-03', preg_match('/class="btn off">Registrar alta \(no hay orden de alta\)/', $html) === 1 && !str_contains($html, 'aria-disabled') && !str_contains($html, 'aria-describedby'), 'F-03: el motivo de un botón desactivado va dentro de su texto; no hay aria-disabled ni aria-describedby');
$w2 = $seccion('wf2');
ver('HAL-04', substr_count($w2, 'chip sel') >= 3 && !str_contains($w2, '✓') && !preg_match('/aria-pressed|role="radio/', $html), sprintf('F-04: %d opciones elegidas en W2 se distinguen solo por relleno y negrita (sin símbolo ni rol de opción)', substr_count($w2, 'chip sel')));
ver('HAL-05', !preg_match('/aria-live|role="(alert|status)"/', $html . $dis), 'F-05: ni los wireframes ni el documento anuncian los resultados y errores a lector de pantalla (aria-live, role alert o status)');
ver('HAL-06', preg_match('/btn pri">Confirmar traslado/', $html) === 1 && preg_match('/btn pri">Confirmar alta/', $html) === 1 && !preg_match('/casilla|entiendo|escriba .{0,12}ALTA/i', $html . $dis), 'F-06: confirmar el alta (cierra el episodio) usa el mismo botón y el mismo paso que confirmar un traslado');
ver('HAL-07', str_contains($dis, 'Pídala al médico') && str_contains($dis, '| Reintentar |') && !preg_match('/ya se guardó|quizá ya|verifique el historial/i', $dis), 'F-07: si se pierde la respuesta y se reintenta, el servidor dice «orden requerida» y el diseño responde «pídala al médico», sin sugerir revisar el historial');
preg_match('/\.t\{[^}]*font-size:(\d+)px/', $css, $te); preg_match('/body\{[^}]*font:(\d+)px/', $css, $cu);
ver('HAL-08', isset($te[1], $cu[1]) && (int) $te[1] < (int) $cu[1], sprintf('F-08: los mensajes de error miden %s px y el cuerpo %s px', $te[1] ?? '?', $cu[1] ?? '?'));
ver('HAL-09', str_contains($wf3, '<th></th>'), sprintf('F-09: la tabla de camas de W3 tiene %d encabezado vacío (la columna del botón)', substr_count($wf3, '<th></th>')));

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
