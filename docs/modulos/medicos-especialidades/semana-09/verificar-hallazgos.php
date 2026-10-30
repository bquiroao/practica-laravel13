<?php
declare(strict_types=1);

// Semana 9: reproduce los hallazgos sobre la captura evaluada.   Uso: php verificar-hallazgos.php [wireframes.html]
// Sin argumento revisa evaluado/wireframes.html y espera que cada hallazgo esté PRESENTE (es la evidencia).
// Con una ruta (los wireframes corregidos) espera que ninguno siga presente (es el criterio de cierre del backlog).
$ruta = $argv[1] ?? __DIR__ . '/evaluado/wireframes.html';
$html = (string) file_get_contents($ruta);
$corregido = isset($argv[1]);
$fallas = 0; $total = 0;
function ver(string $id, bool $presente, string $detalle): void {
    global $corregido, $fallas, $total;
    $ok = $presente !== $corregido; $total++; $fallas += $ok ? 0 : 1;
    printf("%-7s %-5s %-9s %s\n", $id, $ok ? 'PASA' : 'FALLA', $presente ? 'PRESENTE' : 'CORREGIDO', $detalle);
}
// Contraste WCAG 2.x entre dos colores #rrggbb.
function luz(string $c): float {
    $c = ltrim($c, '#'); $c = strlen($c) === 3 ? preg_replace('/./', '$0$0', $c) : $c;   // #444 → #444444
    return array_sum(array_map(fn ($v, $k) => $k * (($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4), array_map('hexdec', str_split($c, 2)), [0.2126, 0.7152, 0.0722]));
}
function razon(string $a, string $b): float { [$x, $y] = [luz($a), luz($b)]; return (max($x, $y) + 0.05) / (min($x, $y) + 0.05); }
preg_match_all('/([.\w][^{}]*)\{([^}]*)\}/', explode('</style>', $html)[0], $r, PREG_SET_ORDER);
$css = []; foreach ($r as [, $sel, $props]) { preg_match_all('/([\w-]+):([^;]+)/', $props, $p, PREG_SET_ORDER); foreach ($p as [, $k, $v]) { $css[trim($sel)][$k] = trim($v); } }
$col = fn (string $s, string $k, string $def) => $css[$s][$k] ?? $def;
$pares = [   // [descripción, texto, fondo, ¿es parte de la interfaz?]
    ['texto sobre la tarjeta', $col('body', 'color', '#222'), $col('.wf', 'background', '#fff'), true],
    ['ayuda gris', $col('.h', 'color', '#555'), $col('.wf', 'background', '#fff'), true],
    ['error rojo', $col('.m', 'color', '#b00020'), $col('.wf', 'background', '#fff'), true],
    ['error rojo sobre aviso', $col('.m', 'color', '#b00020'), $col('.al', 'background', '#fff'), true],
    ['botón principal', $col('.b', 'color', '#fff'), $col('.b', 'background', '#444'), true],
    ['encabezado de tabla', $col('body', 'color', '#222'), $col('th', 'background', '#fff'), true],
    ['insignia numerada (anotación)', $col('.n', 'color', '#fff'), $col('.n', 'background', '#fff'), false],
];
foreach ($pares as [$d, $f, $g, $ui]) { printf("CTR     %-5s %5.2f:1  %s%s\n", razon($f, $g) >= 4.5 ? 'OK' : 'BAJO', razon($f, $g), $d, $ui ? '' : ' (no es interfaz)'); }
$bajos = array_filter($pares, fn ($p) => razon($p[1], $p[2]) < 4.5);

function seccion(string $html, string $id): string { preg_match('#<section class="wf" id="' . $id . '">(.*?)</section>#s', $html, $m); return $m[1] ?? ''; }
ver('HAL-01', str_contains(seccion($html, 'w5'), 'avisar a Citas'), 'W5: el botón promete avisar a Citas y el módulo solo devuelve la lista de citas en conflicto');
ver('HAL-02', str_contains(seccion($html, 'w5'), 'Hasta (no incluido)'), 'W5: «Hasta (no incluido)» invita a un error de un día en la ausencia');
ver('HAL-03', (bool) preg_match('/class="(m|al)"(?![^>]*role=)/', $html) && !str_contains($html, 'aria-live'), 'Mensajes de error y de estado sin role="alert" ni aria-live: un lector de pantalla no los anuncia');
ver('HAL-04', (bool) preg_match('/class="c">\d\d\/\d\d – \d\d\/\d\d</', seccion($html, 'w3')), 'W3: dos fechas en un solo campo, distinto de W5 que usa dos campos');
ver('HAL-05', (bool) preg_match('#\d\d/\d\d – \d\d/\d\d#', seccion($html, 'w3')), 'W3: el rango no muestra el año (02/11 puede ser 2 de noviembre o 11 de febrero)');
ver('HAL-06', substr_count($html, '*') > 0 && !preg_match('/obligatori/i', $html), 'Campos con * sin leyenda que diga qué significa');
ver('HAL-07', str_contains(seccion($html, 'w4'), 'padding:4px 8px'), 'W4: «Ampliar rango» y «Reintentar» son más pequeños que los demás botones');
ver('HAL-08', count($bajos) > 0 && array_values($bajos)[0][3] === false, 'Insignias de anotación con contraste menor a 4.5:1 (solo afecta a quien revisa los wireframes)');
ver('HAL-09', !str_contains(explode('</style>', $html)[0], ':focus'), 'El CSS no define estilo de foco visible: quien navega con teclado no ve dónde está');
$uiBajos = count(array_filter($bajos, fn ($p) => $p[3]));   // control positivo: la interfaz cumple en ambos modos
$total++; $fallas += $uiBajos === 0 ? 0 : 1;
printf("%-7s %-5s %-9s %s\n", 'CTR-01', $uiBajos === 0 ? 'PASA' : 'FALLA', 'CUMPLE', 'Todo el texto de la interfaz alcanza 4.5:1');
printf("\nTotal: %d  Pasan: %d  Fallan: %d\n", $total, $total - $fallas, $fallas);
exit($fallas === 0 ? 0 : 1);
