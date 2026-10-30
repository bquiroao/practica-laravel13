<?php
declare(strict_types=1);

// Mide el contraste WCAG 2.2 (1.4.3 texto, 1.4.11 componentes) de los colores reales del frontend
// y de la paleta propuesta para los estados de cita. Uso: php contraste.php

/** Luminancia relativa de un color #rrggbb, según la definición de WCAG 2.2. */
function luminancia(string $hex): float
{
    $canal = fn (int $v): float => ($c = $v / 255) <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    return 0.2126 * $canal($r) + 0.7152 * $canal($g) + 0.0722 * $canal($b);
}

function contraste(string $a, string $b): float
{
    [$claro, $oscuro] = [max(luminancia($a), luminancia($b)), min(luminancia($a), luminancia($b))];
    return ($claro + 0.05) / ($oscuro + 0.05);
}

// Autoprueba con los extremos que fija la norma: negro sobre blanco 21:1 y un color consigo mismo 1:1.
if (abs(contraste('#000000', '#ffffff') - 21.0) > 0.01 || abs(contraste('#2563eb', '#2563eb') - 1.0) > 0.01) { fwrite(STDERR, "Autoprueba fallida\n"); exit(1); }

$pares = [ // [qué se mide, color, fondo, mínimo WCAG, origen]
    ['Texto del layout', '#0f172a', '#f8fafc', 4.5, 'AppLayout.vue .layout'],
    ['Enlace activo del menú', '#2563eb', '#ffffff', 4.5, 'AppLayout.vue .router-link-active'],
    ['Enlace activo frente a enlace normal', '#2563eb', '#0f172a', 3.0, 'AppLayout.vue (solo cambia el color)'],
    ['Borde de campo de formulario', '#cbd5f5', '#ffffff', 3.0, 'LoginPage.vue .login__input'],
    ['Borde de campo propuesto', '#64748b', '#ffffff', 3.0, 'Backlog B-06'],
    ['Anillo de foco propuesto (2 px)', '#2563eb', '#ffffff', 3.0, 'Backlog B-01'],
    ['Mensaje de error', '#b91c1c', '#ffffff', 4.5, 'LoginPage.vue .login__error'],
    ['Etiqueta de campo', '#334155', '#ffffff', 4.5, 'LoginPage.vue .login__label'],
    ['Mensaje de éxito de W3 (green de PlantUML)', '#008000', '#ffffff', 4.5, 'semana-08-wf3, línea 12'],
    ['Estado «programada» (propuesto)', '#1d4ed8', '#ffffff', 4.5, 'Chips de estado de W1'],
    ['Estado «confirmada» (propuesto)', '#15803d', '#ffffff', 4.5, 'Chips de estado de W1'],
    ['Estado «cancelada» (propuesto)', '#475569', '#ffffff', 4.5, 'HomePage.vue .home__subtitle'],
];
$col = fn (string $s, int $n): string => $s . str_repeat(' ', max(0, $n - mb_strlen($s)));
printf("%s %-9s %-9s %7s %6s  %s\n", $col('Par', 44), 'Color', 'Fondo', 'Razón', 'Mín.', 'Resultado · origen');
foreach ($pares as [$que, $color, $fondo, $minimo, $origen]) {
    $r = contraste($color, $fondo);
    printf("%s %-9s %-9s %6.2f:1 %5.1f  %s · %s\n", $col($que, 44), $color, $fondo, $r, $minimo, $r >= $minimo ? 'cumple' : 'NO cumple', $origen);
}
