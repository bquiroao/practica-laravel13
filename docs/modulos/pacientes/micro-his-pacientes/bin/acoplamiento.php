<?php

declare(strict_types=1);

/*
 * Mide el acoplamiento de cada clase de src/ (semana 7).
 *
 *   Ce  = acoplamiento eferente: cuántas clases del proyecto usa.
 *   Ca  = acoplamiento aferente: cuántas clases del proyecto la usan.
 *   P   = cuántas de las 6 preocupaciones de la tarea mezcla la clase:
 *         identidad · demografía · búsqueda · edición · duplicados · confidencialidad
 *   Flags = parámetros bool de permiso («puede…») que la clase recibe o reenvía.
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

/** Identificadores de código (sin comentarios ni literales) y variables de un archivo. */
function analizar(string $ruta): array
{
    $identificadores = [];
    $variables = [];
    foreach (token_get_all((string) file_get_contents($ruta)) as $t) {
        if (!is_array($t)) {
            continue;
        }
        if (in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            foreach (preg_split('/\\\\/', $t[1]) as $parte) {
                $identificadores[$parte] = true;
            }
        } elseif ($t[0] === T_VARIABLE) {
            $variables[substr($t[1], 1)] = true;
        }
    }

    return [$identificadores, $variables];
}

// Marcas de cada preocupación: clases, métodos, propiedades y constantes que solo usa quien trata ese tema.
$preocupaciones = [
    'identidad' => ['Identificador', 'FabricaIdentificador', 'Dpi', 'Pasaporte', 'identificador', 'identidadProvisional', 'cambiaIdentidadRespectoDe', 'canonico'],
    'demografia' => ['DatosPaciente', 'nombres', 'apellidos', 'nacimiento', 'sexo', 'telefono', 'SEXOS', 'desdeDatos'],
    'busqueda' => ['CriterioBusqueda', 'ConsultaPacientes', 'resumen', 'nombreNormalizado', 'apellidosNormalizados', 'compararPorNombre'],
    'edicion' => ['PoliticaEdicion', 'conDatosDe', 'versionLeida', 'ConflictoVersion', 'actualizar', 'EDITAR', 'EDITAR_RESTRINGIDO'],
    'duplicados' => ['PoliticaDuplicados', 'IndiceDuplicados', 'DocumentoDuplicado', 'titularDe', 'coincidenciasProbables', 'CONFIRMAR_DUPLICADO', 'CODIGO_RESERVADO'],
    'confidencialidad' => ['PoliticaConfidencialidad', 'identidadProtegida', 'seMuestraReducidoPara', 'LEER_PROTEGIDO', 'PROTEGER', 'incluyendoProtegidos', 'protegidos', 'NOMBRE_PROTEGIDO'],
];

$usa = [];
$mezcla = [];
$flags = [];
foreach ($archivos as $clase => $ruta) {
    [$ids, $variables] = analizar($ruta);
    $usa[$clase] = array_values(array_filter(array_keys($archivos), static fn (string $otra): bool => $otra !== $clase && isset($ids[$otra])));
    $mezcla[$clase] = [];
    foreach ($preocupaciones as $nombre => $marcas) {
        if (array_filter($marcas, static fn (string $m): bool => isset($ids[$m]) && $m !== $clase) !== []) {
            $mezcla[$clase][] = $nombre;
        }
    }
    $flags[$clase] = count(array_filter(array_keys($variables), static fn (string $v): bool => str_starts_with($v, 'puede') || $v === 'verProtegidos'));
}

$filas = [];
foreach ($archivos as $clase => $ruta) {
    $filas[] = [
        'clase' => $clase,
        'capa' => explode('/', str_replace('\\', '/', substr($ruta, strlen($raiz) + 1)))[0],
        'ce' => count($usa[$clase]),
        'ca' => count(array_filter($usa, static fn (array $u): bool => in_array($clase, $u, true))),
        'flags' => $flags[$clase],
        'preocupaciones' => $mezcla[$clase],
    ];
}
usort($filas, static fn (array $a, array $b): int => [count($b['preocupaciones']), $b['ce']] <=> [count($a['preocupaciones']), $a['ce']]);

$n = (int) ($argv[1] ?? 12);
printf("Micro-HIS Pacientes — acoplamiento por clase (%d clases en src/)\n", count($filas));
echo str_repeat('-', 118) . PHP_EOL;
printf("%-26s %-13s %4s %4s %6s  %-3s %s\n", 'Clase', 'Capa', 'Ce', 'Ca', 'Flags', 'P', 'Preocupaciones que mezcla');
foreach (array_slice($filas, 0, $n) as $f) {
    printf("%-26s %-13s %4d %4d %6d  %-3d %s\n", $f['clase'], $f['capa'], $f['ce'], $f['ca'], $f['flags'],
        count($f['preocupaciones']), implode(' · ', $f['preocupaciones']));
}
echo str_repeat('-', 118) . PHP_EOL;

$totalCe = array_sum(array_column($filas, 'ce'));
printf("Ce total: %d · Ce medio: %.2f · Clases que mezclan ≥ 4 preocupaciones: %d · Clases con flags de permiso: %d (%d flags)\n",
    $totalCe, $totalCe / max(1, count($filas)),
    count(array_filter($filas, static fn (array $f): bool => count($f['preocupaciones']) >= 4)),
    count(array_filter($filas, static fn (array $f): bool => $f['flags'] > 0)),
    array_sum(array_column($filas, 'flags')));
