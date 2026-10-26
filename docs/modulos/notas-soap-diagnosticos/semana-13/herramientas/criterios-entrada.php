<?php

declare(strict_types=1);

/**
 * Semana 13 · Puerta de entrada de la revisión técnica: verifica E-1..E-6 (E-7 y E-8 los anota el moderador en el acta).
 * Uso: php herramientas/criterios-entrada.php   (desde semana-13). Termina con código 1 si algún criterio falla.
 */
$aqui = dirname(__DIR__);
$micro = dirname($aqui) . '/micro-his-notas-soap';
$SESIONES = [
    'S1' => ['src/Dominio', 'src/Aplicacion', 'src/Persistencia/Pdo', 'database/schema.sql'],
    'S2' => ['src/Presentacion', 'api/openapi.yaml', 'bootstrap/contenedor.php', 'config/config.php', 'public/index.php'],
];
// Líneas efectivas: sin líneas en blanco ni comentarios.
$efectivas = static function (string $f): int {
    $n = 0;
    $bloque = false;
    foreach (file($f) ?: [] as $l) {
        $s = trim($l);
        if ($bloque || str_starts_with($s, '/*')) {
            $bloque = !str_contains($s, '*/');
            continue;
        }
        $n += ($s !== '' && !preg_match('~^(//|\*|#|--)~', $s)) ? 1 : 0;
    }
    return $n;
};
$archivos = static fn (string $ruta): array => is_file($ruta) ? [$ruta] : array_map('strval', iterator_to_array(
    new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS)), '/\.php$/'), false));

$git = static fn (string $args): string => trim((string) shell_exec('git -C ' . escapeshellarg($micro) . " $args 2>&1"));
$cambios = $git('status --porcelain -- .');
$res['E-1'] = [$cambios === '', 'commit ' . $git('rev-parse --short HEAD') . ($cambios === '' ? ', sin cambios locales en micro-his-notas-soap/' : ', HAY cambios locales')];

$salida = (string) shell_exec('cd ' . escapeshellarg($micro) . ' && php tests/ejecutar.php');
preg_match('/Total: \d+\s+Pasan: \d+\s+Fallan: (\d+)\s+Omitidas: \d+/', $salida, $m);
$res['E-2'] = [($m[1] ?? '') === '0', $m[0] ?? 'la suite no terminó'];
preg_match_all('/\[(PASA |FALLA|OMITE)\] ((?:API|ARQ)-\d+)/', $salida, $guardas, PREG_SET_ORDER);
$malas = array_filter($guardas, static fn (array $g): bool => $g[1] !== 'PASA ');
$res['E-3'] = [count($guardas) >= 20 && $malas === [], count($guardas) . ' guardas de contrato (API) y de capas (ARQ), ' . count($malas) . ' sin pasar'];

$tamanos = array_map(static fn (array $rutas): int => array_sum(array_map($efectivas, array_merge(...array_map(static fn ($r) => $archivos("$micro/$r"), $rutas)))), $SESIONES);
$res['E-4'] = [max($tamanos) <= 1600, sprintf('S1 = %d y S2 = %d líneas efectivas (máximo 1600 por sesión)', $tamanos['S1'], $tamanos['S2'])];

$items = preg_match_all('/^\| RT-\d\d /m', (string) @file_get_contents("$aqui/checklist-revision.md"));
$csv = (string) @file_get_contents("$aqui/plantillas/registro-hallazgos.csv");
$res['E-5'] = [$items >= 12 && $items <= 15 && str_starts_with($csv, 'id,'), "checklist con $items ítems; plantilla de hallazgos " . ($csv !== '' ? 'publicada' : 'no existe')];
$propios = preg_match_all('/^\| AR-\d\d /m', (string) @file_get_contents("$aqui/autorrevision-autor.md"));
$res['E-6'] = [$propios > 0, "$propios hallazgos de la autorrevisión del autor"];

foreach ($res as $id => [$ok, $detalle]) {
    printf("%s  %s  %s\n", $ok ? 'PASA ' : 'FALLA', $id, $detalle);
}
$fallan = count(array_filter($res, static fn (array $r): bool => !$r[0]));
echo PHP_EOL . 'Criterios automáticos: ' . count($res) . "  Fallan: $fallan  (E-7 y E-8: manuales)" . PHP_EOL;
exit($fallan > 0 ? 1 : 0);
