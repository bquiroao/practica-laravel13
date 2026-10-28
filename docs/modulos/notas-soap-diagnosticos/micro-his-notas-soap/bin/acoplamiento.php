<?php

declare(strict_types=1);

/*
 * Semana 7: mide el acoplamiento de cada clase de src/ para elegir con datos qué refactorizar.
 *
 *   Ce = acoplamiento eferente: cuántas clases del proyecto usa.
 *   Ca = acoplamiento aferente: cuántas clases del proyecto la usan.
 *   P  = cuántas de las 6 preocupaciones del módulo mezcla la clase:
 *        nota · diagnóstico · acceso clínico · expediente · bitácora · persistencia
 *
 *   php bin/acoplamiento.php [n]     (n = filas a mostrar, por defecto 12)
 *
 * Solo lee el código con el tokenizador de PHP; no ejecuta nada del micro-HIS.
 */

$raiz = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src';
$archivos = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $f) {
    if ($f->getExtension() === 'php' && !str_contains(str_replace('\\', '/', $f->getPathname()), '/plantillas/')) {
        $archivos[basename($f->getPathname(), '.php')] = $f->getPathname();
    }
}
ksort($archivos);

/** @return array{0: array<string, true>, 1: list<string>} identificadores de código y literales de texto */
function analizar(string $ruta): array
{
    $identificadores = [];
    $literales = [];
    foreach (token_get_all((string) file_get_contents($ruta)) as $t) {
        if (!is_array($t)) {
            continue;
        }
        if (in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            foreach (explode('\\', $t[1]) as $parte) {
                $identificadores[$parte] = true;
            }
        } elseif ($t[0] === T_CONSTANT_ENCAPSED_STRING) {
            $literales[] = trim($t[1], '\'"');
        }
    }

    return [$identificadores, $literales];
}

$preocupaciones = [
    'nota' => ['NotaSoap', 'SeccionesSoap', 'RepositorioNotas'],
    'diagnostico' => ['Diagnostico', 'TipoDiagnostico', 'RepositorioDiagnosticos'],
    'acceso' => ['UsuarioClinico', 'VerificadorAcceso', 'AccesoDenegado'],
    'expediente' => ['RepositorioExpedientes', 'ExpedienteNoDisponible'],
    'bitacora' => ['RegistroAuditoria', 'BandejaSalida', 'EventoClinico'],
    'persistencia' => ['Transaccion', 'ErrorPersistencia', 'RegistroIdempotencia'],
];

$usa = [];
$mezcla = [];
foreach ($archivos as $clase => $ruta) {
    [$ids, $literales] = analizar($ruta);
    $usa[$clase] = array_values(array_filter(array_keys($archivos), static fn (string $otra): bool => $otra !== $clase && isset($ids[$otra])));
    $mezcla[$clase] = [];
    foreach ($preocupaciones as $nombre => $marcas) {
        $hay = array_filter($marcas, static fn (string $m): bool => isset($ids[$m]) && $m !== $clase);
        // Un evento de bitácora escrito como literal ('NOTA_FIRMADA') también cuenta.
        if ($nombre === 'bitacora' && preg_grep('/^NOTA_[A-Z_]+$/', $literales)) {
            $hay[] = 'literal';
        }
        if ($hay !== []) {
            $mezcla[$clase][] = $nombre;
        }
    }
}

$filas = [];
foreach ($archivos as $clase => $ruta) {
    $relativa = str_replace('\\', '/', substr($ruta, strlen($raiz) + 1));
    $filas[] = [
        'clase' => $clase,
        'capa' => explode('/', $relativa)[0],
        'ce' => count($usa[$clase]),
        'ca' => count(array_filter($usa, static fn (array $u): bool => in_array($clase, $u, true))),
        'lineas' => count(file($ruta) ?: []),
        'p' => $mezcla[$clase],
    ];
}
usort($filas, static fn (array $a, array $b): int => [count($b['p']), $b['ce'], $a['clase']] <=> [count($a['p']), $a['ce'], $b['clase']]);

$n = (int) ($argv[1] ?? 12);
printf("Micro-HIS Notas SOAP — acoplamiento por clase (%d clases en src/)\n", count($filas));
echo str_repeat("-", 121) . PHP_EOL;
printf("%-33s %-13s %4s %4s %6s  %-2s %s\n", 'Clase', 'Capa', 'Ce', 'Ca', 'Líneas', 'P', 'Preocupaciones que mezcla');
foreach (array_slice($filas, 0, $n) as $f) {
    printf("%-33s %-13s %4d %4d %6d  %-2d %s\n", $f['clase'], $f['capa'], $f['ce'], $f['ca'], $f['lineas'], count($f['p']), implode(' · ', $f['p']));
}
echo str_repeat("-", 121) . PHP_EOL;

// Duplicación entre casos de uso: líneas de código idénticas (25 o más caracteres) en 2 o más archivos.
$apariciones = [];
foreach ($archivos as $clase => $ruta) {
    if (!str_contains(str_replace('\\', '/', $ruta), '/Aplicacion/CasosDeUso/')) {
        continue;
    }
    foreach (array_unique(array_map('trim', file($ruta) ?: [])) as $linea) {
        // Se ignoran declaraciones y firmas: solo cuentan las líneas de lógica.
        if (strlen($linea) >= 25 && preg_match('/^(use |namespace |\*|\/\/|\/\*\*|declare|private readonly|public readonly|public function __construct\()/', $linea) !== 1) {
            $apariciones[$linea][] = $clase;
        }
    }
}
$duplicadas = array_filter($apariciones, static fn (array $clases): bool => count($clases) >= 2);
printf("Líneas de lógica repetidas entre casos de uso: %d\n", count($duplicadas));
foreach ($duplicadas as $linea => $clases) {
    printf("  [%s] %s\n", implode(', ', $clases), mb_strimwidth($linea, 0, 90, '…'));
}
$totalCe = array_sum(array_column($filas, 'ce'));
printf("Ce total: %d · Ce medio: %.2f · Clases que mezclan 4 o más preocupaciones: %d\n",
    $totalCe, $totalCe / max(1, count($filas)), count(array_filter($filas, static fn (array $f): bool => count($f['p']) >= 4)));
