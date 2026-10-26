<?php

declare(strict_types=1);

/*
 * Mide el acoplamiento de cada clase de src/ (semana 7).
 *
 *   Ce  = acoplamiento eferente: cuántas clases del proyecto usa.
 *   Ca  = acoplamiento aferente: cuántas clases del proyecto la usan.
 *   Preocupaciones = cuántas de las 6 preocupaciones RBAC mezcla la clase:
 *     roles · permisos · rutas · decisión · auditoría · persistencia
 *
 *   php bin/acoplamiento.php [n]     (n = filas a mostrar, por defecto 12)
 */

$raiz = dirname(__DIR__) . '/src';
$archivos = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $f) {
    if ($f->getExtension() === 'php') {
        $archivos[basename($f->getPathname(), '.php')] = $f->getPathname();
    }
}

/** Identificadores de código (sin comentarios ni literales) y literales de texto de un archivo. */
function analizar(string $ruta): array
{
    $identificadores = [];
    $literales = [];
    foreach (token_get_all((string) file_get_contents($ruta)) as $t) {
        if (!is_array($t)) {
            continue;
        }
        if (in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            foreach (preg_split('/\\\\/', $t[1]) as $parte) {
                $identificadores[$parte] = true;
            }
        } elseif ($t[0] === T_CONSTANT_ENCAPSED_STRING) {
            $literales[] = trim($t[1], '\'"');
        }
    }

    return [$identificadores, $literales];
}

$preocupaciones = [
    'roles' => ['Membresia', 'RepositorioMembresias', 'SolicitudAsignacion', 'SolicitudRevocacion', 'PoliticaAsignacion', 'PoliticaRevocacion', 'ReglaAsignacion', 'ReglaRevocacion'],
    'permisos' => ['CatalogoPermisos', 'PERMISO_REQUERIDO'],
    'rutas' => ['Ruta', 'Rutas', 'Peticion', 'Respuesta', 'Kernel'],
    'decision' => ['AutorizarOperacion', 'ComandoAutorizar', 'ResultadoAutorizacion', 'PoliticaAutorizacion', 'ContextoAutorizacion', 'SolicitudAutorizacion'],
    'auditoria' => ['EventoAuditoria', 'RegistroAuditoria'],
    'persistencia' => ['Transaccion', 'ErrorPersistencia', 'MembresiaDuplicada'],
];

$usa = [];
$mezcla = [];
foreach ($archivos as $clase => $ruta) {
    [$ids, $literales] = analizar($ruta);
    $usa[$clase] = array_values(array_filter(array_keys($archivos), static fn (string $otra): bool => $otra !== $clase && isset($ids[$otra])));
    $mezcla[$clase] = [];
    foreach ($preocupaciones as $nombre => $marcas) {
        $hay = array_filter($marcas, static fn (string $m): bool => isset($ids[$m]) && $m !== $clase);
        // Un permiso escrito como literal ('roles.asignar') también cuenta.
        if ($nombre === 'permisos' && preg_grep('/^[a-z_]+\.[a-z_]+$/', $literales)) {
            $hay[] = 'literal';
        }
        if ($hay !== []) {
            $mezcla[$clase][] = $nombre;
        }
    }
}

$filas = [];
foreach ($archivos as $clase => $ruta) {
    $ca = count(array_filter($usa, static fn (array $u): bool => in_array($clase, $u, true)));
    $filas[] = [
        'clase' => $clase,
        'capa' => explode('/', str_replace('\\', '/', substr($ruta, strlen($raiz) + 1)))[0],
        'ce' => count($usa[$clase]),
        'ca' => $ca,
        'preocupaciones' => $mezcla[$clase],
    ];
}
usort($filas, static fn (array $a, array $b): int => [count($b['preocupaciones']), $b['ce']] <=> [count($a['preocupaciones']), $a['ce']]);

$n = (int) ($argv[1] ?? 12);
printf("Micro-HIS RBAC — acoplamiento por clase (%d clases en src/)\n", count($filas));
echo str_repeat('-', 110) . PHP_EOL;
printf("%-30s %-13s %4s %4s  %-3s %s\n", 'Clase', 'Capa', 'Ce', 'Ca', 'P', 'Preocupaciones RBAC que mezcla');
foreach (array_slice($filas, 0, $n) as $f) {
    printf("%-30s %-13s %4d %4d  %-3d %s\n", $f['clase'], $f['capa'], $f['ce'], $f['ca'], count($f['preocupaciones']), implode(' · ', $f['preocupaciones']));
}
echo str_repeat('-', 110) . PHP_EOL;

// Duplicación en la capa de Aplicación: líneas de código idénticas (≥ 25 caracteres) en 2 o más archivos.
$apariciones = [];
foreach ($archivos as $clase => $ruta) {
    if (!str_contains(str_replace('\\', '/', $ruta), '/Aplicacion/')) {
        continue;
    }
    foreach (array_unique(array_map('trim', file($ruta) ?: [])) as $linea) {
        if (strlen($linea) >= 25 && !preg_match('/^(use |namespace |\*|\/\/|\/\*\*|declare)/', $linea)) {
            $apariciones[$linea][] = $clase;
        }
    }
}
$duplicadas = array_filter($apariciones, static fn (array $clases): bool => count($clases) >= 2);
printf("Líneas duplicadas entre archivos de Aplicación: %d\n", count($duplicadas));

$totalCe = array_sum(array_column($filas, 'ce'));
printf("Ce total: %d · Ce medio: %.2f · Clases que mezclan ≥ 4 preocupaciones: %d\n",
    $totalCe, $totalCe / max(1, count($filas)), count(array_filter($filas, static fn (array $f): bool => count($f['preocupaciones']) >= 4)));
