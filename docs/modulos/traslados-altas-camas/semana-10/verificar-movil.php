<?php
declare(strict_types=1);

// Semana 10 — Comprueba que el diseño móvil es coherente con el diseño de la semana 8 y con el código, y cumple sus propias reglas.
// Uso desde la raíz del repo: php docs/modulos/traslados-altas-camas/semana-10/verificar-movil.php
$base = dirname(__DIR__); $micro = "$base/micro-his-traslados"; $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$leer = fn (string $r) => (string) @file_get_contents($r);
$html = $leer(__DIR__ . '/wireframes-movil/moviles.html'); $doc = $leer(__DIR__ . '/semana-10-diseno-movil.md'); $s8 = $leer("$base/semana-08/semana-08-diseno-ux.md");
$codigo = implode("\n", array_map($leer, glob("$micro/src/*/*.php")));
if ($html === '' || $doc === '' || $s8 === '' || $codigo === '') { echo "Faltan los wireframes móviles, este documento, el diseño de la semana 8 o el código del Micro-HIS.\n"; exit(1); }
$css = (string) (preg_match('/<style>(.*?)<\/style>/s', $html, $m) ? $m[1] : '');
$seccion = fn (string $id) => (string) (preg_match('/<section class="wf" id="' . $id . '">.*?<\/section>/s', $html, $x) ? $x[0] : '');

preg_match_all('/<section class="wf" id="(m\d)"/', $html, $pant); preg_match('/\.tel\{width:(\d+)px/', $css, $w);
ver('MOV-01', count($pant[1]) >= 3 && count($pant[1]) <= 5 && isset($w[1]) && $w[1] >= 320 && $w[1] <= 430, sprintf('%d pantallas móviles (3 a 5) dibujadas a %s px (rango 320 a 430)', count($pant[1]), $w[1] ?? '?'));
preg_match('/\.btn\{[^}]*min-height:(\d+)px/', $css, $b);
ver('MOV-02', isset($b[1]) && (int) $b[1] >= 44, sprintf('Botones de %s px de alto (mínimo 44 px)', $b[1] ?? '?'));
preg_match_all('/font(?:-size)?:\s*(?:bold\s+)?(\d+)px/', $css, $f);
ver('MOV-03', $f[1] !== [] && min($f[1]) >= 14, sprintf('Letra mínima de %d px (mínimo 14)', $f[1] ? min($f[1]) : 0));

// Estados de cama de la semana 8: todos se ven en M2 con texto y solo la «Disponible» tiene botón
preg_match_all('/^\| ([^|]+) \| `(\w+)` \| (Sí|No) \|$/m', $s8, $est, PREG_SET_ORDER); $m2 = $seccion('m2');
$sinTexto = array_filter($est, fn ($e) => !str_contains($m2, '>' . trim($e[1]) . '<'));
$elegibles = array_filter($est, fn ($e) => $e[3] === 'Sí');
ver('MOV-04', count($est) === 4 && $sinTexto === [] && substr_count($m2, 'btn pri">Elegir cama ') === count($elegibles), sprintf('Los %d estados de cama de la semana 8 están en M2 con texto y hay %d botón «Elegir cama …» por %d estado elegible%s', count($est), substr_count($m2, 'btn pri">Elegir cama '), count($elegibles), $sinTexto ? ' · falta: ' . implode(', ', array_map(fn ($e) => trim($e[1]), $sinTexto)) : ''));

// Los 12 códigos de error de la semana 8 tienen tratamiento móvil
preg_match_all('/^\| `(\w+)` \| \d{3} \| .{10,} \| .+ \|$/m', (string) strstr($s8, '## 4.'), $c8); preg_match_all('/^\| `(\w+)` \| \d{3} \| .{10,} \| M\d \|$/m', $doc, $cm);
$a = array_unique($c8[1]); $d = array_unique($cm[1]); sort($a); sort($d);
ver('MOV-05', count($a) === 12 && $a === $d, sprintf('Los %d códigos de error de la semana 8 tienen tratamiento en móvil (distintos: %s)', count($a), implode(', ', array_merge(array_diff($a, $d), array_diff($d, $a))) ?: 'ninguno'));
$m3 = $seccion('m3');
ver('MOV-06', str_contains($m3, 'Confirmar traslado') && str_contains($m3, 'Confirmar alta') && str_contains($m3, 'ban w') && str_contains($m3, 'no se puede deshacer') && str_contains($m3, 'Todo se guarda junto'), 'M3 muestra la confirmación del traslado («todo o nada») y la del alta (borde punteado y «no se puede deshacer»)');
ver('MOV-07', preg_match('/\| 320 a 479 px \|.*\n\| 480 a 767 px \|.*\n\| 768 px o más \|.*semana 8/', $doc) === 1, 'Hay tres rangos de ancho (320, 480 y 768 px) y el último es el diseño de escritorio de la semana 8');
ver('MOV-08', preg_match('/## \d+\. Escenario 1.*?Decisión de contenido.*?Decisión de error.*?## \d+\. Escenario 2.*?Decisión de contenido.*?Decisión de error/s', $doc) === 1, 'Los dos escenarios móviles tienen decisión de contenido y decisión de error');
ver('MOV-09', !preg_match('/localStorage|sessionStorage|cookie/i', $html) && str_contains($html, 'No cierre esta pantalla') && str_contains($doc, 'no se guardan en el almacenamiento del navegador'), 'Los datos no se guardan en el navegador y M5 avisa que no se cierre la pantalla');
$png = array_filter($pant[1], fn ($p) => is_file(__DIR__ . "/wireframes-movil/semana-10-$p.png"));
ver('MOV-10', count($pant[1]) > 0 && count($png) === count($pant[1]), sprintf('Existen las %d imágenes de las pantallas', count($png)));
// El reintento tras guardar choca con la orden ya aplicada (por eso M5 manda primero al historial)
ver('MOV-11', preg_match("/ordenes_movimiento SET estado = \?[^;]*\['aplicada'/", $codigo) === 1 && str_contains($codigo, "ErrorDominio('orden_requerida'") && str_contains($seccion('m5'), 'Ver historial'), 'El servidor marca la orden «aplicada» al guardar y rechaza un segundo envío con orden_requerida; por eso M5 manda primero al historial');
echo $fallas === 0 ? "\nResultado: el diseño móvil cumple lo que afirma.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
