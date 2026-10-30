<?php
declare(strict_types=1);

// Semana 9 — Mide la accesibilidad y la usabilidad del diseño de la semana 8 (wireframes y documento).
//   CL-xx: puntos que se cumplen.  HAL-xx: hallazgos que se reproducen tal como los describe el documento.
//   PASA = la afirmación del documento es cierta. Cuando se corrija el diseño, el HAL correspondiente pasa a FALLA y el backlog se actualiza.
// Uso desde la raíz del repo: php docs/modulos/signos-vitales-alertas/semana-09/verificar-accesibilidad.php
$s8 = dirname(__DIR__) . '/semana-08'; $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$html = (string) @file_get_contents("$s8/wireframes/wireframes.html"); $dis = (string) @file_get_contents("$s8/semana-08-diseno-ux.md");
$informe = (string) @file_get_contents(__DIR__ . '/semana-09-evaluacion-usabilidad-accesibilidad.md');
if ($html === '' || $dis === '') { echo "No está el diseño de la semana 8 (semana-08/); esta evaluación lo necesita.\n"; exit(1); }

$color = function (string $sel, string $prop) use ($html): ?string {   // color de una regla del <style> de los wireframes
    if (!preg_match('/(?<![\w.>])' . preg_quote($sel, '/') . '\{[^}]*?(?<![\w-])' . $prop . ':\s*(?:[^;}]*?\s)?(#[0-9a-f]{3,6})/i', $html, $m)) { return null; }
    return strlen($m[1]) === 4 ? '#' . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2] . $m[1][3] . $m[1][3] : strtolower($m[1]);
};
$luz = function (string $c): float { $v = array_map(fn ($h) => hexdec($h) / 255, str_split(substr($c, 1), 2)); $v = array_map(fn ($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $v); return 0.2126 * $v[0] + 0.7152 * $v[1] + 0.0722 * $v[2]; };
$contraste = function (string $a, string $b) use ($luz): float { [$x, $y] = [$luz($a), $luz($b)]; return (max($x, $y) + 0.05) / (min($x, $y) + 0.05); };
$fondo = $color('.p', 'background') ?? '#ffffff';
$pares = ['texto de ayuda' => [$color('.hint', 'color'), $fondo], 'mensaje de error' => [$color('.t', 'color'), $fondo], 'etiqueta del panel' => [$color('.p>b', 'color'), $fondo],
          'botón principal' => [$color('.pri', 'color'), $color('.pri', 'background')], 'nivel crítico' => [$color('.crit', 'color'), $color('.crit', 'background')], 'número de nota' => [$color('i', 'color'), $color('i', 'background')]];
$razon = array_map(fn ($p) => $p[0] && $p[1] ? $contraste($p[0], $p[1]) : 0.0, $pares);
$malos = array_keys(array_filter($razon, fn ($r) => $r < 4.5));
ver('CL-01', count($pares) === 6 && $malos === [], 'Contraste de texto ≥ 4.5:1 en los 6 pares principales (menor: ' . sprintf('%.1f', min($razon)) . ':1)');
$borde = $color('.in', 'border') ?? '';
ver('CL-02', $borde !== '' && $contraste($borde, $fondo) >= 3, sprintf('Borde de los campos %.1f:1 sobre el fondo (mínimo 3:1 para componentes de interfaz)', $borde ? $contraste($borde, $fondo) : 0));
ver('CL-03', preg_match('/\.btn\{[^}]*padding:(\d+)px/', $html, $p) && preg_match('/border:(\d+)px solid #333;padding/', $html, $b) && 14 * 1.35 + 2 * $p[1] + 2 * $b[1] >= 24, sprintf('Botones de %.0f px de alto, sobre el mínimo de 24 px de WCAG 2.2', 14 * 1.35 + 2 * ($p[1] ?? 0) + 2 * ($b[1] ?? 0)));

// Hallazgos
$off = $color('.off', 'color'); $rOff = $off ? $contraste($off, $fondo) : 99;
ver('HAL-01', $rOff < 4.5, sprintf('F-07: el texto «Guardando…» (botón desactivado) tiene contraste %.1f:1, menor que 4.5:1', $rOff));
$cuerpo = preg_replace('/<style.*?<\/style>/s', '', $html); preg_match_all('/[▲△✓⚠]/u', $cuerpo, $sim);
ver('HAL-02', count($sim[0]) >= 10 && !str_contains($cuerpo, 'aria-hidden'), sprintf('F-05: %d símbolos (▲ △ ✓ ⚠) en los wireframes y ninguno se oculta al lector de pantalla (aria-hidden)', count($sim[0])));
ver('HAL-03', preg_match_all('/teclado|\bTab\b|\bEsc\b|\bEnter\b|foco visible/i', $dis . $html) === 0, 'F-01: el diseño de la semana 8 no menciona teclado, Tab, Esc, Enter ni foco visible (0 menciones)');
$w6 = substr($html, (int) strpos($html, 'id="wf6"')); $w3 = substr($html, (int) strpos($html, 'id="wf3"'), (int) strpos($html, 'id="wf4"') - (int) strpos($html, 'id="wf3"'));
ver('HAL-04', substr_count($html, 'foco') === 1 && !str_contains($w6, 'foco') && !str_contains($w3, 'foco'), 'F-01: el manejo del foco se define solo en W4; ni la confirmación de W6 ni «Guardando…» de W3 dicen adónde va el foco');
ver('HAL-05', preg_match('/label|etiqueta|describedby/i', $dis) === 0 && str_contains($dis, '| Rango posible |'), 'F-06: la unidad y el rango están en columnas aparte y el diseño no los asocia al campo (0 menciones de label, etiqueta o aria-describedby)');
ver('HAL-06', str_contains($dis, 'es un número') && preg_match('/escriba solo números|no es un número/i', $dis) === 0, 'F-03: se valida «es un número», pero ninguno de los 10 mensajes dice qué hacer con texto o coma decimal');
ver('HAL-07', str_contains($html, 'class="hint"><i>6</i>Aviso'), 'F-04: el aviso «la sistólica suele ser mayor que la diastólica» usa el mismo estilo gris pequeño de la ayuda');
ver('HAL-08', str_contains($dis, 'no se conservan') && preg_match('/extender|renovar|antes de que (termine|venza)/i', $dis) === 0, 'F-02: al terminar la sesión (401) se pierden los valores y no se avisa antes del cierre');
$altoBtn = isset($p[1]) ? 14 * 1.35 + 2 * $p[1] + 2 * ($b[1] ?? 0) : 0; preg_match_all('/font(?:-size)?:\s*(?:bold\s+)?(\d+)px/', $html, $f);
ver('HAL-09', $altoBtn < 44 && count(array_filter($f[1], fn ($x) => (int) $x < 12)) >= 1, sprintf('F-08: botones de %.0f px (menos de 44 px recomendados en móvil) y %d tamaños de letra menores de 12 px', $altoBtn, count(array_filter($f[1], fn ($x) => (int) $x < 12))));

// El informe lista los mismos hallazgos
$enInforme = array_unique((preg_match_all('/HAL-\d{2}/', $informe, $m) ? $m[0] : []));
preg_match_all('/ver\(\'(HAL-\d{2})\'/', (string) file_get_contents(__FILE__), $enScript);
ver('DOC-01', $informe !== '' && array_diff($enScript[1], $enInforme) === [] && preg_match_all('/^\| F-\d{2} \|/m', $informe) >= 6, sprintf('El informe cubre los %d hallazgos del script y su backlog tiene al menos 6 filas F-xx', count($enScript[1])));
echo $fallas === 0 ? "\nResultado: todo lo que afirma la evaluación se cumple.\n" : "\nResultado: $fallas afirmaciones no se cumplen.\n";
exit($fallas ? 1 : 0);
