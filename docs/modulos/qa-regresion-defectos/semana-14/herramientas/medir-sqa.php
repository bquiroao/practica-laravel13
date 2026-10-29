<?php

declare(strict_types=1);

/*
 * Semana 14 · Tablero del plan SQA. Calcula las métricas M-1..M-6 desde sus fuentes y compara cada una con su umbral.
 *   QA_DB_DSN="pgsql:..." php herramientas/medir-sqa.php [--rapido]
 * M-1, M-3, M-4 y M-5 se calculan al correr; M-2 y M-6 se leen de la última corrida de su herramienta (son lentas),
 * y el tablero muestra la fecha de esa corrida: una métrica vieja es una alerta en sí misma (más de 7 días → VENCIDA).
 * --rapido: M-5 solo en memoria (sin PostgreSQL).
 * Código de salida: cantidad de métricas fuera de umbral.
 */

$modulo = dirname(__DIR__, 2);
$qa = $modulo . '/micro-his-qa';
require $qa . '/bootstrap/contenedor.php';
require $qa . '/bin/instalar-base.php';
foreach (glob($qa . '/tests/Dobles/*.php') ?: [] as $archivo) {
    require_once $archivo;
}

use Qa\Persistencia\FabricaAdaptadores;
use Qa\Persistencia\Pdo\FabricaConexion;

$rapido = in_array('--rapido', $argv, true);
$dsn = getenv('QA_DB_DSN') ?: null;
$filas = [];
/** @param bool|null $cumple null = sin dato */
function metrica(string $id, string $nombre, string $valor, string $umbral, ?bool $cumple, string $fuente): void
{
    global $filas;
    $filas[] = [$id, $nombre, $valor, $umbral, $cumple === null ? 'SIN DATO' : ($cumple ? 'OK' : 'FUERA'), $fuente];
}
function fechaDe(string $archivo): string
{
    return is_file($archivo) ? date('Y-m-d', (int) filemtime($archivo)) : '—';
}
function vencida(string $archivo): bool
{
    return !is_file($archivo) || time() - (int) filemtime($archivo) > 7 * 86400;
}

// M-1 · Pruebas automáticas en verde ------------------------------------------------------------------------------
$salida = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($qa . '/tests/ejecutar.php') . ' 2>&1', $salida);
preg_match('/Total: (\d+)\s+Pasan: (\d+)\s+Fallan: (\d+)\s+Omitidas: (\d+)/', implode("\n", $salida), $m);
$total = (int) ($m[1] ?? 0);
$pasan = (int) ($m[2] ?? 0);
$omitidas = (int) ($m[4] ?? 0);
metrica('M-1', 'Pruebas en verde', sprintf('%d/%d%s', $pasan, $total, $omitidas ? " ($omitidas omitidas)" : ''), '100 %, 0 omitidas',
    $total > 0 && $pasan === $total && $omitidas === 0, $dsn ? 'tests/ejecutar.php con PostgreSQL' : 'tests/ejecutar.php SIN PostgreSQL');

// M-2 · Puntaje de mutación (última corrida) ------------------------------------------------------------------------
// La corrida más reciente de cualquier semana (semana 15: el plan se sigue usando después de su semana).
$corridas = glob($modulo . '/semana-*/evidencia/mutaciones.txt') ?: [];
usort($corridas, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
$archivoMut = $corridas[0] ?? $modulo . '/semana-14/evidencia/mutaciones.txt';
$texto = is_file($archivoMut) ? (string) file_get_contents($archivoMut) : '';
preg_match('/Mutaciones: (\d+)\s+Detectadas: (\d+)/', $texto, $m);
$det = isset($m[2]) ? (int) $m[2] : null;
metrica('M-2', 'Mutaciones detectadas', $det === null ? '—' : "{$m[2]}/{$m[1]}", '100 % y corrida ≤ 7 días',
    $det === null ? null : $det === (int) $m[1] && !vencida($archivoMut), 'tests/mutaciones.php, corrida del ' . fechaDe($archivoMut) . ' (' . basename(dirname($archivoMut, 2)) . ')');

// M-3 · Reglas de negocio con prueba con nombre ----------------------------------------------------------------------
$descripciones = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($qa . '/tests', FilesystemIterator::SKIP_DOTS)) as $f) {
    if (str_ends_with((string) $f, '.php')) {
        preg_match_all("/prueba\('[A-Z]+-\d+',\s*'([^']*)'/", (string) file_get_contents((string) $f), $mm);
        $descripciones .= implode("\n", $mm[1]) . "\n";
    }
}
$sinPrueba = array_values(array_filter(array_map(static fn (int $i): string => sprintf('R-%02d', $i), range(1, 15)),
    static fn (string $r): bool => !preg_match('/\b' . $r . '\b/', $descripciones)));
metrica('M-3', 'Reglas R-01..R-15 con prueba', sprintf('%d/15%s', 15 - count($sinPrueba), $sinPrueba ? ' (faltan ' . implode(', ', $sinPrueba) . ')' : ''),
    '15/15', $sinPrueba === [], 'descripciones de prueba() en tests/');

// M-4 · Hallazgos críticos de revisión que se siguen reproduciendo ----------------------------------------------------
$salida = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($modulo . '/semana-13/herramientas/sonda-revision.php') . ' 2>&1', $salida);
$criticos = ['RT-01'];
$abiertos = array_values(array_filter($criticos, static fn (string $id): bool => in_array("SE REPRODUCE  $id", $salida, true)));
$mayores = count(array_filter(['RT-02', 'RT-04', 'RT-05'], static fn (string $id): bool => in_array("SE REPRODUCE  $id", $salida, true)));
metrica('M-4', 'Hallazgos críticos de revisión abiertos', sprintf('%d críticos (%s), %d mayores', count($abiertos), implode(', ', $abiertos) ?: '—', $mayores),
    '0 críticos', $abiertos === [], 'semana-13/herramientas/sonda-revision.php');

// M-5 · RNF-08: trazabilidad de una campaña de 500 casos ------------------------------------------------------------
function campanaGrande(array $p, int $n): array
{
    $uc = construirCasosDeUso($p + ['reloj' => new RelojFijo()]);
    $ids = ['TC-107', 'TC-108'];
    for ($i = 1; count($ids) < $n; $i++) {
        $id = sprintf('TC-%04d', 1000 + $i);
        $uc['disenar']->ejecutar('ana-qa', 'hosp-norte', ['id' => $id, 'titulo' => "Caso de carga $i", 'precondiciones' => '-',
            'pasos' => [['accion' => 'Ejecutar', 'esperado' => 'Pasa']], 'prioridad' => 'MEDIA', 'requisitos' => [sprintf('RF-%02d', 1 + $i % 14)]]);
        $uc['aprobar']->ejecutar('lider-qa', 'hosp-norte', $id);
        $ids[] = $id;
    }
    $c = $uc['planificar']->ejecutar('lider-qa', 'hosp-norte', ['nombre' => 'Carga RNF-08', 'funcionalidades' => ['expediente'], 'casos' => $ids, 'umbral' => 0.95]);
    $uc['iniciar']->ejecutar('lider-qa', 'hosp-norte', $c->datos['id'], '1.4.0+abc1234');
    foreach ($ids as $k => $id) {
        $falla = $k % 50 === 0;
        $uc['registrar']->ejecutar('tito-tester', 'hosp-norte', $c->datos['id'], sprintf('carga-%06d', $k), ['caso' => $id, 'resultado' => $falla ? 'FALLA' : 'PASA']
            + ($falla ? ['evidencias' => [['tipo' => 'registro', 'bytes' => "falla $id"]], 'defecto' => ['titulo' => "Falla $id", 'pasos' => ['x'], 'severidad' => 'MENOR']] : []));
    }
    $tiempos = [];
    for ($r = 0; $r < 20; $r++) {
        $t = hrtime(true);
        $s = $uc['trazabilidad']->ejecutar('lider-qa', 'hosp-norte', $c->datos['id']);
        $tiempos[] = (hrtime(true) - $t) / 1e6;
    }
    sort($tiempos);

    return ['p95' => $tiempos[18], 'casos' => count($s->datos['casos'] ?? []), 'codigo' => $s->codigo];
}
$config = ['registro_acceso' => 'ninguno', 'evidencias' => sys_get_temp_dir() . '/qa-sqa-' . getmypid()] + (require $qa . '/config/config.php');
$mem = campanaGrande(FabricaAdaptadores::memoria($config, require $qa . '/database/datos-demo.php'), 500);
$valor = sprintf('memoria p95 %.0f ms (%d casos)', $mem['p95'], $mem['casos']);
$cumple = $mem['casos'] === 500 && $mem['p95'] < 1000;
$fuente = 'campaña sintética de 500 casos, 20 consultas';
if (!$rapido && $dsn !== null) {
    $pdo = FabricaConexion::crear($dsn, $config['db']['usuario'], $config['db']['clave']);
    instalarBase($pdo);
    $pg = campanaGrande(FabricaAdaptadores::crear(['persistencia' => 'pdo'] + $config, $pdo), 500);
    $valor .= sprintf(' · PostgreSQL p95 %.0f ms (%d casos)', $pg['p95'], $pg['casos']);
    $cumple = $cumple && $pg['casos'] === 500 && $pg['p95'] < 1000;
} else {
    $valor .= ' · PostgreSQL sin medir';
}
metrica('M-5', 'RNF-08: trazabilidad de 500 casos', $valor, 'p95 < 1000 ms en PostgreSQL', $cumple, $fuente);

// M-6 · Accesibilidad del prototipo (última corrida) ---------------------------------------------------------------
$archivoA11y = $modulo . '/semana-12/evidencia/cambio-2-despues-verde.txt';
$texto = is_file($archivoA11y) ? (string) file_get_contents($archivoA11y) : '';
preg_match('/Comprobaciones: (\d+)\s+Fallan: (\d+)\s+Violaciones axe-core: (\d+)/', $texto, $m);
metrica('M-6', 'Accesibilidad: violaciones de axe-core', isset($m[3]) ? "{$m[3]} violaciones, {$m[2]} de {$m[1]} comprobaciones fallan" : '—',
    '0 violaciones y corrida ≤ 7 días', isset($m[3]) ? (int) $m[3] === 0 && (int) $m[2] === 0 && !vencida($archivoA11y) : null,
    'semana-12/herramientas/recorrido.cjs, corrida del ' . fechaDe($archivoA11y));

// Tablero -------------------------------------------------------------------------------------------------------------
printf("Tablero SQA · %s\n\n", date('Y-m-d H:i'));
$fuera = 0;
foreach ($filas as [$id, $nombre, $valor, $umbral, $estado, $fuente]) {
    $fuera += $estado === 'OK' ? 0 : 1;
    printf("%-4s %-6s %s\n       valor:  %s\n       umbral: %s\n       fuente: %s\n\n", $id, $estado, $nombre, $valor, $umbral, $fuente);
}
echo "Fuera de umbral o sin dato: $fuera de " . count($filas) . "\n";
exit($fuera);
