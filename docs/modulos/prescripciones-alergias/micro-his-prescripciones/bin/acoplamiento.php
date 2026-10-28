<?php

declare(strict_types=1);

/*
 * Mide el acoplamiento de cada clase de src/ (semana 7).
 *
 *   Ce    = acoplamiento eferente: cuántas clases del proyecto usa.
 *   Ca    = acoplamiento aferente: cuántas clases del proyecto la usan.
 *   Ramas = if, foreach, for, while, case, catch, ?:, ??, && y || (aproximación de la complejidad).
 *   Dc    = decisiones CLÍNICAS que la clase toma por su cuenta: comparar con BLOQUEO o ADVERTENCIA, mirar «evaluado»,
 *           el mínimo de la justificación o la confirmación «sin alergias». En el Dominio es lo esperado; en otra capa, no.
 *   P     = cuántas de las 6 preocupaciones de la tarea mezcla la clase:
 *           prescripciones · medicamentos · dosis · alergias · validación previa · bloqueo seguro
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

/** Identificadores de código (sin comentarios ni literales), ramas y decisiones clínicas de un archivo. */
function analizar(string $ruta): array
{
    $identificadores = [];
    $ramas = 0;
    $clinicas = 0;
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
                $clinicas += in_array($parte, ['BLOQUEO', 'ADVERTENCIA', 'evaluado', 'MIN_JUSTIFICACION', 'confirmaSinAlergias'], true) ? 1 : 0;
            }
        }
    }

    return [$identificadores, $ramas, $clinicas];
}

// Marcas de cada preocupación: clases, métodos y propiedades que solo usa quien trata ese tema.
$preocupaciones = [
    'prescripciones' => ['Prescripcion', 'RepositorioPrescripciones', 'porClave', 'huella', 'conId'],
    'medicamentos' => ['Medicamento', 'CatalogoMedicamentos', 'buscar', 'grupoDelAlergeno'],
    'dosis' => ['ValidadorPrescripcion', 'dosisMaximaDiaria', 'validar'],
    'alergias' => ['Alergia', 'ExpedienteAlergias', 'FuenteAlergias', 'alergiasDe', 'activas', 'alergeno'],
    'validacion previa' => ['VerificadorAlergias', 'ResultadoVerificacion', 'EvaluacionClinica', 'evaluar', 'verificar'],
    'bloqueo seguro' => ['FuenteAlergiasNoDisponible', 'BLOQUEO', 'codigoBloqueo', 'impideJustificar', 'MIN_JUSTIFICACION', 'PERMISO_OVERRIDE',
        'confirmaSinAlergias', 'PoliticaEmision'],
];

$usa = [];
$mezcla = [];
$medidas = [];
foreach ($archivos as $clase => $ruta) {
    [$ids, $ramas, $clinicas] = analizar($ruta);
    $usa[$clase] = array_values(array_filter(array_keys($archivos), static fn (string $otra): bool => $otra !== $clase && isset($ids[$otra])));
    $mezcla[$clase] = [];
    foreach ($preocupaciones as $nombre => $marcas) {
        if (array_filter($marcas, static fn (string $m): bool => isset($ids[$m]) && $m !== $clase) !== []) {
            $mezcla[$clase][] = $nombre;
        }
    }
    $medidas[$clase] = [$ramas, $clinicas];
}

$filas = [];
foreach ($archivos as $clase => $ruta) {
    $filas[] = [
        'clase' => $clase,
        'capa' => explode('/', str_replace('\\', '/', substr($ruta, strlen($raiz) + 1)))[0],
        'ce' => count($usa[$clase]),
        'ca' => count(array_filter($usa, static fn (array $u): bool => in_array($clase, $u, true))),
        'ramas' => $medidas[$clase][0],
        'dc' => $medidas[$clase][1],
        'preocupaciones' => $mezcla[$clase],
    ];
}
usort($filas, static fn (array $a, array $b): int => [count($b['preocupaciones']), $b['ce']] <=> [count($a['preocupaciones']), $a['ce']]);

$n = (int) ($argv[1] ?? 14);
printf("Micro-HIS Prescripciones — acoplamiento por clase (%d clases en src/)\n", count($filas));
echo str_repeat('-', 128) . PHP_EOL;
printf("%-30s %-13s %4s %4s %6s %4s  %-2s %s\n", 'Clase', 'Capa', 'Ce', 'Ca', 'Ramas', 'Dc', 'P', 'Preocupaciones que mezcla');
foreach (array_slice($filas, 0, $n) as $f) {
    printf("%-30s %-13s %4d %4d %6d %4d  %-2d %s\n", $f['clase'], $f['capa'], $f['ce'], $f['ca'], $f['ramas'], $f['dc'],
        count($f['preocupaciones']), implode(' · ', $f['preocupaciones']));
}
echo str_repeat('-', 128) . PHP_EOL;

// En Persistencia, nombrar «evaluado» o «confirmaSinAlergias» es mapear una columna, no decidir: se cuentan Aplicación y Presentación.
$fueraDelDominio = array_filter($filas, static fn (array $f): bool => in_array($f['capa'], ['Aplicacion', 'Presentacion'], true));
printf("Ce total: %d · Clases que mezclan ≥ 4 preocupaciones: %d\n", array_sum(array_column($filas, 'ce')),
    count(array_filter($filas, static fn (array $f): bool => count($f['preocupaciones']) >= 4)));
printf("Decisiones clínicas (Dc) en Aplicación y Presentación: %d · en el Dominio: %d\n", array_sum(array_column($fueraDelDominio, 'dc')),
    array_sum(array_column(array_filter($filas, static fn (array $f): bool => $f['capa'] === 'Dominio'), 'dc')));
