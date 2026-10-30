<?php
declare(strict_types=1);

// Semana 10 — Comprueba que el diseño móvil es coherente con el diseño de la semana 8 y cumple sus propias reglas.
// Uso desde la raíz del repo: php docs/modulos/signos-vitales-alertas/semana-10/verificar-movil.php
$base = dirname(__DIR__); $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$leer = fn (string $r) => (string) @file_get_contents($r);
$html = $leer(__DIR__ . '/wireframes-movil/moviles.html'); $doc = $leer(__DIR__ . '/semana-10-diseno-movil.md'); $s8 = $leer("$base/semana-08/semana-08-diseno-ux.md");
if ($html === '' || $doc === '' || $s8 === '') { echo "Faltan los wireframes móviles, este documento o el diseño de la semana 8.\n"; exit(1); }
$css = (string) (preg_match('/<style>(.*?)<\/style>/s', $html, $m) ? $m[1] : '');

preg_match_all('/<section class="wf" id="(m\d)"/', $html, $pant); preg_match('/\.tel\{width:(\d+)px/', $css, $w);
ver('MOV-01', count($pant[1]) >= 3 && count($pant[1]) <= 5 && isset($w[1]) && $w[1] >= 320 && $w[1] <= 430, sprintf('%d pantallas móviles (3 a 5) dibujadas a %s px (rango 320 a 430)', count($pant[1]), $w[1] ?? '?'));
preg_match('/\.btn\{[^}]*min-height:(\d+)px/', $css, $b); preg_match('/\.campo\{[^}]*height:(\d+)px/', $css, $c);
ver('MOV-02', isset($b[1], $c[1]) && min($b[1], $c[1]) >= 44, sprintf('Botones de %s px y campos de %s px de alto (mínimo 44 px)', $b[1] ?? '?', $c[1] ?? '?'));
preg_match_all('/font(?:-size)?:\s*(?:bold\s+)?(\d+)px/', $css, $f); preg_match('/\.campo\{[^}]*font-size:(\d+)px/', $css, $fc);
ver('MOV-03', $f[1] !== [] && min($f[1]) >= 14 && ($fc[1] ?? 0) >= 16, sprintf('Letra mínima de %d px (mínimo 14) y de %s px en los campos (mínimo 16)', $f[1] ? min($f[1]) : 0, $fc[1] ?? '?'));

// Los 6 signos del formulario móvil son los de la semana 8
preg_match_all('/^\| (?:PAS|PAD|FC|FR|Temp|SpO2) \| ([^|]+) \| (\S+) \| (\d+ a \d+) \|$/m', $s8, $sg, PREG_SET_ORDER);
$m2 = (string) (preg_match('/<section class="wf" id="m2">.*?<\/section>/s', $html, $x) ? $x[0] : '');
$faltan = array_filter($sg, fn ($s) => !str_contains($m2, trim($s[1]) . ' (' . $s[2] . ')') || !str_contains($m2, 'Rango posible: ' . $s[3]));
ver('MOV-04', count($sg) === 6 && $faltan === [] && substr_count($m2, 'data-inputmode="decimal"') === 6, sprintf('Los 6 signos de la semana 8 están en M2 con unidad, rango y teclado decimal (%d campos)%s', substr_count($m2, 'data-inputmode="decimal"'), $faltan ? ' · falta: ' . implode(', ', array_map(fn ($s) => trim($s[1]), $faltan)) : ''));

// Los 10 códigos de error de la semana 8 tienen tratamiento móvil
preg_match_all('/^\| `(\w+)` \| \d{3} \| .{10,} \| .+ \|$/m', (string) strstr($s8, '## 4.'), $c8); preg_match_all('/^\| `(\w+)` \| \d{3} \| .{10,} \| M\d \|$/m', $doc, $cm);
$a = array_unique($c8[1]); $d = array_unique($cm[1]); sort($a); sort($d);
ver('MOV-05', count($a) === 10 && $a === $d, sprintf('Los %d códigos de error de la semana 8 tienen tratamiento en móvil (distintos: %s)', count($a), implode(', ', array_merge(array_diff($a, $d), array_diff($d, $a))) ?: 'ninguno'));
$niv = ['✓', 'Normal', '△', 'ANORMAL', '▲', 'CRÍTICA', '?', 'Sin rango']; $sinNivel = array_filter($niv, fn ($n) => !str_contains($html, $n));
ver('MOV-06', $sinNivel === [], 'Los 4 niveles (normal, anormal, crítica, sin rango) se ven en móvil con símbolo y texto' . ($sinNivel ? ' · falta: ' . implode(', ', $sinNivel) : ''));
ver('MOV-07', preg_match('/\| 320 a 479 px \|.*\n\| 480 a 767 px \|.*\n\| 768 px o más \|.*semana 8/', $doc) === 1, 'Hay tres rangos de ancho (320, 480 y 768 px) y el último es el diseño de escritorio de la semana 8');
ver('MOV-08', preg_match('/## \d+\. Escenario 1.*?Decisión de contenido.*?Decisión de error.*?## \d+\. Escenario 2.*?Decisión de contenido.*?Decisión de error/s', $doc) === 1, 'Los dos escenarios móviles tienen decisión de contenido y decisión de error');
ver('MOV-09', !preg_match('/localStorage|sessionStorage|cookie/i', $html) && str_contains($html, 'No cierre esta pantalla') && str_contains($doc, 'no se guardan en el almacenamiento del navegador'), 'Los valores no se guardan en el navegador y M5 avisa que no se cierre la pantalla');
$png = array_filter($pant[1], fn ($p) => is_file(__DIR__ . "/wireframes-movil/semana-10-$p.png"));
ver('MOV-10', count($pant[1]) > 0 && count($png) === count($pant[1]), sprintf('Existen las %d imágenes de las pantallas', count($png)));
echo $fallas === 0 ? "\nResultado: el diseño móvil cumple lo que afirma.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
