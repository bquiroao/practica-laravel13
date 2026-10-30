<?php
declare(strict_types=1);

// Acoplamiento entre los tipos propios del Micro-HIS del módulo (semana 7). Para cada clase, interfaz o enum de src/:
// Ce = tipos propios que usa (eferente), Ca = tipos propios que lo usan (aferente) e I = Ce / (Ca + Ce).
// El punto con mayor acoplamiento es el de mayor Ca + Ce. Uso: php medir-acoplamiento.php (desde cualquier carpeta).
$raiz = glob(dirname(__DIR__, 2) . '/micro-his-*', GLOB_ONLYDIR)[0] ?? exit("El Micro-HIS del módulo no está en esta rama.\n");
$capas = []; $menciones = [];   // tipo => capa; tipo => [nombres que aparecen en su cuerpo]
foreach (glob("$raiz/src/*/*.php") as $archivo) {
    $t = array_values(array_filter(token_get_all(file_get_contents($archivo)), fn ($x) => !is_array($x) || $x[0] !== T_WHITESPACE));
    $actual = null;
    foreach ($t as $i => $x) {
        if (!is_array($x)) continue;
        if (in_array($x[0], [T_CLASS, T_INTERFACE, T_ENUM], true) && ($t[$i + 1][0] ?? null) === T_STRING && ($t[$i - 1][0] ?? null) !== T_NEW) {
            $actual = $t[$i + 1][1]; $capas[$actual] = basename(dirname($archivo)); $menciones[$actual] ??= [];
        } elseif ($actual !== null && in_array($x[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            $menciones[$actual][] = substr(strrchr('\\' . $x[1], '\\'), 1);   // Aplicacion\RepositorioX -> RepositorioX
        }
    }
}
$usa = []; $usadoPor = array_fill_keys(array_keys($capas), []);
foreach ($menciones as $tipo => $nombres) {
    $usa[$tipo] = array_values(array_unique(array_filter($nombres, fn ($n) => isset($capas[$n]) && $n !== $tipo)));
    foreach ($usa[$tipo] as $otro) $usadoPor[$otro][] = $tipo;
}
uksort($capas, fn ($a, $b) => [count($usa[$b]) + count($usadoPor[$b]), $a] <=> [count($usa[$a]) + count($usadoPor[$a]), $b]);
printf("%-28s %-13s %3s %3s %5s  %s\n", 'Tipo', 'Capa', 'Ce', 'Ca', 'I', 'Usa');
foreach ($capas as $tipo => $capa) {
    $ce = count($usa[$tipo]); $ca = count($usadoPor[$tipo]);
    printf("%-28s %-13s %3d %3d %5s  %s\n", $tipo, $capa, $ce, $ca, $ce + $ca === 0 ? '-' : number_format($ce / ($ce + $ca), 2), implode(', ', $usa[$tipo]) ?: '-');
}
echo 'Mayor acoplamiento: ' . array_key_first($capas) . "\n";
