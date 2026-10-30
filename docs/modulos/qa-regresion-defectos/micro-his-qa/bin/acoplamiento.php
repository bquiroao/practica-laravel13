<?php

declare(strict_types=1);

/*
 * Mide el acoplamiento de cada clase de src/ (semana 7).
 *
 *   Ce    = acoplamiento eferente: cuántas clases del proyecto usa.
 *   Ca    = acoplamiento aferente: cuántas clases del proyecto la usan.
 *   Ramas = if, foreach, for, while, case, catch, ?:, ??, && y || (aproximación de la complejidad).
 *   Dq    = decisiones de QA que la clase toma por su cuenta: preguntar si un resultado exige evidencia o defecto, comparar
 *           con FALLA / NO_APLICA, el tamaño máximo de una evidencia o la severidad propuesta. En el Dominio es lo esperado;
 *           en Aplicación o Presentación, no.
 *   P     = cuántas de las 6 preocupaciones de la tarea mezcla la clase:
 *           campañas · matriz · defectos · severidad · evidencia · trazabilidad
 *   LOC   = líneas no vacías.
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

/** Identificadores de código (sin comentarios ni literales), ramas, decisiones de QA y líneas de un archivo. */
function analizar(string $ruta): array
{
    $identificadores = [];
    $ramas = 0;
    $decisiones = 0;
    $ramificadores = [T_IF, T_ELSEIF, T_FOREACH, T_FOR, T_WHILE, T_CASE, T_CATCH, T_COALESCE, T_BOOLEAN_AND, T_BOOLEAN_OR];
    $codigo = (string) file_get_contents($ruta);
    $tokens = token_get_all($codigo);
    foreach ($tokens as $i => $t) {
        if (!is_array($t)) {
            $ramas += $t === '?' && is_array($tokens[$i + 1] ?? null) && $tokens[$i + 1][0] === T_WHITESPACE ? 1 : 0;
            continue;
        }
        if (in_array($t[0], $ramificadores, true)) {
            $ramas++;
        } elseif (in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            foreach (preg_split('/\\\\/', $t[1]) as $parte) {
                $identificadores[$parte] = true;
                $decisiones += in_array($parte, ['exigeEvidencia', 'FALLA', 'NO_APLICA', 'TAMANO_MAXIMO', 'tryFrom'], true) ? 1 : 0;
            }
        }
    }
    $loc = count(array_filter(explode("\n", $codigo), static fn (string $l): bool => trim($l) !== ''));

    return [$identificadores, $ramas, $decisiones, $loc];
}

// Marcas de cada preocupación: clases y métodos que solo usa quien trata ese tema.
$preocupaciones = [
    'campañas' => ['Campana', 'RepositorioCampanas', 'admite', 'siguienteNumero', 'agregarIntento', 'Intento'],
    'matriz' => ['criticosDe', 'marcarEnMatriz'],
    'defectos' => ['Defecto', 'RepositorioDefectos', 'reportar', 'verificar', 'marcarCorregido'],
    'severidad' => ['Severidad', 'clasificar', 'criticoAbierto', 'mayoresAbiertos'],
    'evidencia' => ['EvidenciaVerificable', 'FabricaEvidencias', 'AlmacenEvidencias', 'EvidenciaRegistrada', 'ReferenciaExterna', 'Clasificacion'],
    'trazabilidad' => ['requisitos', 'queCubren', 'RegistroAuditoria'],
];

$usa = [];
$medidas = [];
foreach ($archivos as $clase => $ruta) {
    [$ids, $ramas, $dq, $loc] = analizar($ruta);
    $usa[$clase] = array_values(array_filter(array_keys($archivos), static fn (string $otra): bool => $otra !== $clase && isset($ids[$otra])));
    $mezcla = [];
    foreach ($preocupaciones as $nombre => $marcas) {
        if (array_filter($marcas, static fn (string $m): bool => isset($ids[$m]) && $m !== $clase) !== []) {
            $mezcla[] = $nombre;
        }
    }
    $medidas[$clase] = [$ramas, $dq, $loc, $mezcla];
}

$filas = [];
foreach ($archivos as $clase => $ruta) {
    $filas[] = [
        'clase' => $clase,
        'capa' => explode('/', str_replace('\\', '/', substr($ruta, strlen($raiz) + 1)))[0],
        'ce' => count($usa[$clase]),
        'ca' => count(array_filter($usa, static fn (array $u): bool => in_array($clase, $u, true))),
        'ramas' => $medidas[$clase][0],
        'dq' => $medidas[$clase][1],
        'loc' => $medidas[$clase][2],
        'preocupaciones' => $medidas[$clase][3],
    ];
}
usort($filas, static fn (array $a, array $b): int => [count($b['preocupaciones']), $b['ce']] <=> [count($a['preocupaciones']), $a['ce']]);

$n = (int) ($argv[1] ?? 14);
printf("Micro-HIS QA — acoplamiento por clase (%d clases en src/)\n", count($filas));
echo str_repeat('-', 132) . PHP_EOL;
printf("%-28s %-13s %4s %4s %6s %4s %5s  %-2s %s\n", 'Clase', 'Capa', 'Ce', 'Ca', 'Ramas', 'Dq', 'LOC', 'P', 'Preocupaciones que mezcla');
foreach (array_slice($filas, 0, $n) as $f) {
    printf("%-28s %-13s %4d %4d %6d %4d %5d  %-2d %s\n", $f['clase'], $f['capa'], $f['ce'], $f['ca'], $f['ramas'], $f['dq'], $f['loc'],
        count($f['preocupaciones']), implode(' · ', $f['preocupaciones']));
}
echo str_repeat('-', 132) . PHP_EOL;
$fueraDelDominio = array_filter($filas, static fn (array $f): bool => in_array($f['capa'], ['Aplicacion', 'Presentacion'], true));
printf("Ce total: %d · LOC total de src/: %d · Clases que mezclan ≥ 4 preocupaciones: %d\n", array_sum(array_column($filas, 'ce')),
    array_sum(array_column($filas, 'loc')), count(array_filter($filas, static fn (array $f): bool => count($f['preocupaciones']) >= 4)));
printf("Decisiones de QA (Dq) en Aplicación y Presentación: %d · en el Dominio: %d\n", array_sum(array_column($fueraDelDominio, 'dq')),
    array_sum(array_column(array_filter($filas, static fn (array $f): bool => $f['capa'] === 'Dominio'), 'dq')));
