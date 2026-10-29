<?php
declare(strict_types=1);

// Contraste WCAG 2.1 (1.4.3 texto ≥ 4.5:1; 1.4.11 componentes y foco ≥ 3:1) de los colores de
// micro-his/src/Presentation/Web/vistas/layout.php. Uso: php contraste.php

function luminancia(string $hex): float
{
    $c = array_map(fn ($h) => hexdec($h) / 255, str_split(ltrim($hex, '#'), 2));
    $c = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

function contraste(string $a, string $b): float
{
    [$l1, $l2] = [luminancia($a), luminancia($b)];
    return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
}

$pares = [
    // [uso, primer plano, fondo, mínimo]
    ['Texto general', '#1f2933', '#f5f7fa', 4.5],
    ['Texto de ayuda .aviso', '#52606d', '#f5f7fa', 4.5],
    ['Texto de ayuda .aviso sobre la alerta blanca', '#52606d', '#ffffff', 4.5],
    ['Texto del botón', '#ffffff', '#1d4e89', 4.5],
    ['Texto de la alerta', '#1f2933', '#ffffff', 4.5],
    ['Borde de la alerta (componente)', '#b91c1c', '#ffffff', 3.0],
    ['Contorno de foco sobre el fondo', '#b45309', '#f5f7fa', 3.0],
    ['Texto de error rojo (wireframe W-01)', '#b91c1c', '#ffffff', 4.5],
    ['Enlace (color por defecto del navegador)', '#0000ee', '#f5f7fa', 4.5],
];
foreach ($pares as [$uso, $fg, $bg, $min]) {
    $r = contraste($fg, $bg);
    $uso .= str_repeat(' ', max(0, 46 - mb_strlen($uso)));
    printf("%s %s sobre %s  %5.2f:1  mínimo %.1f:1  %s\n", $uso, $fg, $bg, $r, $min, $r >= $min ? 'CUMPLE' : 'NO CUMPLE');
}
