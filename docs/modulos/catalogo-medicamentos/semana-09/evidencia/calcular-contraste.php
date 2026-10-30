<?php
declare(strict_types=1);

// Contraste WCAG 2.x de los colores de micro-his/src/Presentation/Web/Vistas/layout.php.
// Uso: php calcular-contraste.php [--evidencia=contraste.txt]

function luminancia(string $hex): float
{
    $c = array_map(fn ($p) => hexdec($p) / 255, str_split(ltrim($hex, '#'), 2));
    $c = array_map(fn ($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

function contraste(string $a, string $b): float
{
    [$x, $y] = [luminancia($a), luminancia($b)];
    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

// [uso, primer plano, fondo, mínimo exigido]
$pares = [
    ['Texto normal', '#1b1f24', '#ffffff', 4.5],
    ['Enlaces y color primario', '#0b5cad', '#ffffff', 4.5],
    ['Texto del botón principal', '#ffffff', '#0b5cad', 4.5],
    ['Mensajes de error', '#a4161a', '#ffffff', 4.5],
    ['Texto sobre caja de alerta', '#1b1f24', '#fbeeee', 4.5],
    ['Texto del botón de peligro', '#ffffff', '#a4161a', 4.5],
    ['Texto sobre caja de aviso', '#1b1f24', '#eef7f0', 4.5],
    ['Borde de campos (no texto, 1.4.11)', '#6b7280', '#ffffff', 3.0],
    ['Indicador de foco actual (no texto, 1.4.11)', '#f2b705', '#ffffff', 3.0],
    ['Indicador de foco propuesto (B-01)', '#1d4ed8', '#ffffff', 3.0],
];

$lineas = ['Contraste WCAG 2.x - Catálogo de medicamentos (semana 9)', ''];
foreach ($pares as [$uso, $a, $b, $min]) {
    $r = contraste($a, $b);
    $etiqueta = $uso . str_repeat(' ', max(0, 45 - mb_strlen($uso)));
    $lineas[] = sprintf('%s %s sobre %s = %5.2f:1 (mínimo %.1f:1) %s', $etiqueta, $a, $b, $r, $min, $r >= $min ? 'CUMPLE' : 'NO CUMPLE');
}
$texto = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $texto;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--evidencia=')) {
        file_put_contents(substr($arg, 12), $texto);
    }
}
