<?php

declare(strict_types=1);

/*
 * Mide el acoplamiento de cada clase de src/ (semana 7).
 *
 *   Ce    = acoplamiento eferente: cuántas clases del proyecto usa.
 *   Ca    = acoplamiento aferente: cuántas clases del proyecto la usan.
 *   Ramas = if, foreach, for, while, case, catch, ?:, ??, && y || (aproximación de la complejidad).
 *   Alc   = veces que la clase DECIDE el alcance de tenant por su cuenta: el comodín RED o una variable/propiedad «red».
 *   P     = cuántas de las 6 preocupaciones de la tarea mezcla la clase:
 *           inmutabilidad · usuario · módulo · acción · tenant · rango de fechas
 *
 *   php bin/acoplamiento.php [n]     (n = filas a mostrar, por defecto 14)
 */

$raiz = dirname(__DIR__) . '/src';
$archivos = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $f) {
    if ($f->getExtension() === 'php') {
        $archivos[basename($f->getPathname(), '.php')] = $f->getPathname();
    }
}

/** Identificadores de código (sin comentarios ni literales), ramas y decisiones de alcance de un archivo. */
function analizar(string $ruta): array
{
    $identificadores = [];
    $ramas = 0;
    $alcance = 0;
    $ramificadores = [T_IF, T_ELSEIF, T_FOREACH, T_FOR, T_WHILE, T_CASE, T_CATCH, T_COALESCE, T_BOOLEAN_AND, T_BOOLEAN_OR];
    $tokens = token_get_all((string) file_get_contents($ruta));
    foreach ($tokens as $i => $t) {
        if (!is_array($t)) {
            // Ternario: «? » con espacio; el «?» de un tipo anulable (?string) va pegado.
            $ramas += $t === '?' && is_array($tokens[$i + 1] ?? null) && $tokens[$i + 1][0] === T_WHITESPACE ? 1 : 0;
            continue;
        }
        if (in_array($t[0], $ramificadores, true)) {
            $ramas++;
        } elseif (in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            foreach (preg_split('/\\\\/', $t[1]) as $parte) {
                $identificadores[$parte] = true;
                $alcance += in_array($parte, ['RED', 'red'], true) ? 1 : 0;
            }
        } elseif ($t[0] === T_VARIABLE && $t[1] === '$red') {
            $alcance++;
        }
    }

    return [$identificadores, $ramas, $alcance];
}

// Marcas de cada preocupación: clases, métodos, propiedades y constantes que solo usa quien trata ese tema.
$preocupaciones = [
    'inmutabilidad' => ['Sello', 'CadenaSellada', 'anexar', 'sellado', 'sello', 'bloquearCadena', 'GENESIS', 'corrigiendo', 'auditarLectura'],
    'usuario' => ['FiltroUsuario', 'usuario', 'actor'],
    'modulo' => ['FiltroModulo', 'ReglaModuloDelEmisor', 'modulo', 'emisor'],
    'accion' => ['FiltroAccion', 'CatalogoAcciones', 'ReglaAccionEnCatalogo', 'accion'],
    'tenant' => ['RED', 'red', 'hospital', 'PERMISO_RED', 'ReglaTenantDelEmisor', 'puedeActuarEn', 'Alcance'],
    'fechas' => ['FiltroRangoFechas', 'ocurridoEn', 'despuesDe', 'leerCursor', 'cursorDe', 'Cursor'],
];

$usa = [];
$mezcla = [];
$medidas = [];
foreach ($archivos as $clase => $ruta) {
    [$ids, $ramas, $alcance] = analizar($ruta);
    $usa[$clase] = array_values(array_filter(array_keys($archivos), static fn (string $otra): bool => $otra !== $clase && isset($ids[$otra])));
    $mezcla[$clase] = [];
    foreach ($preocupaciones as $nombre => $marcas) {
        if (array_filter($marcas, static fn (string $m): bool => isset($ids[$m]) && $m !== $clase) !== []) {
            $mezcla[$clase][] = $nombre;
        }
    }
    $medidas[$clase] = [$ramas, $alcance];
}

$filas = [];
foreach ($archivos as $clase => $ruta) {
    $filas[] = [
        'clase' => $clase,
        'capa' => explode('/', str_replace('\\', '/', substr($ruta, strlen($raiz) + 1)))[0],
        'ce' => count($usa[$clase]),
        'ca' => count(array_filter($usa, static fn (array $u): bool => in_array($clase, $u, true))),
        'ramas' => $medidas[$clase][0],
        'alc' => $medidas[$clase][1],
        'preocupaciones' => $mezcla[$clase],
    ];
}
usort($filas, static fn (array $a, array $b): int => [count($b['preocupaciones']), $b['ce']] <=> [count($a['preocupaciones']), $a['ce']]);

$n = (int) ($argv[1] ?? 14);
printf("Micro-HIS Auditoría — acoplamiento por clase (%d clases en src/)\n", count($filas));
echo str_repeat('-', 122) . PHP_EOL;
printf("%-24s %-13s %4s %4s %6s %4s  %-2s %s\n", 'Clase', 'Capa', 'Ce', 'Ca', 'Ramas', 'Alc', 'P', 'Preocupaciones que mezcla');
foreach (array_slice($filas, 0, $n) as $f) {
    printf("%-24s %-13s %4d %4d %6d %4d  %-2d %s\n", $f['clase'], $f['capa'], $f['ce'], $f['ca'], $f['ramas'], $f['alc'],
        count($f['preocupaciones']), implode(' · ', $f['preocupaciones']));
}
echo str_repeat('-', 122) . PHP_EOL;

$totalCe = array_sum(array_column($filas, 'ce'));
$casos = array_filter($filas, static fn (array $f): bool => $f['capa'] === 'Aplicacion');
printf("Ce total: %d · Ce medio: %.2f · Clases que mezclan ≥ 4 preocupaciones: %d\n",
    $totalCe, $totalCe / max(1, count($filas)), count(array_filter($filas, static fn (array $f): bool => count($f['preocupaciones']) >= 4)));
printf("Decisiones de alcance (Alc) en Aplicación: %d · en todo src/: %d\n",
    array_sum(array_column($casos, 'alc')), array_sum(array_column($filas, 'alc')));
