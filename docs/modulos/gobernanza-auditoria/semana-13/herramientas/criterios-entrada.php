<?php

declare(strict_types=1);

/**
 * Semana 13 · Verifica los criterios de ENTRADA de la revisión técnica formal de Auditoría
 * y arma el «paquete de revisión»: archivos, líneas efectivas y commit congelado por sesión.
 *
 * Uso: php herramientas/criterios-entrada.php   (desde docs/modulos/gobernanza-auditoria/semana-13)
 * Salida: PASA / FALLA / MANUAL por criterio. Código 1 si algún criterio automático falla.
 */
$semana = dirname(__DIR__);
$micro = dirname($semana) . '/micro-his-auditoria';
$repo = trim((string) shell_exec('git -C ' . escapeshellarg($micro) . ' rev-parse --show-toplevel'));
const MAX_LINEAS_SESION = 1600;   // ≈ 2 h de preparación a 150–200 LOC/h para cada revisor, una sesión de ≤ 90 min

$sesiones = [
    'S1 · Reglas de dominio del movimiento (inmutabilidad, sello, filtros, corrección)' => [
        'src/Dominio', 'database/schema.sql',
    ],
    'S2 · Aplicación y persistencia (transacción, cadena, meta-auditoría, adaptadores)' => [
        'src/Aplicacion', 'src/Persistencia/Pdo', 'src/Persistencia/EnMemoria', 'database/integracion-central.sql',
    ],
    'S3 · Contrato, frontera HTTP, permisos/tenant y dependencias' => [
        'api/openapi.yaml', 'src/Presentacion/Http', 'bootstrap/contenedor.php', 'config/config.php', 'public/index.php',
    ],
];

function archivosDe(string $base, array $rutas): array
{
    $lista = [];
    foreach ($rutas as $r) {
        $p = "$base/$r";
        if (is_dir($p)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && preg_match('/\.(php|sql|yaml)$/', $f->getFilename())) {
                    $lista[] = str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
                }
            }
        } elseif (is_file($p)) {
            $lista[] = $r;
        }
    }
    sort($lista);
    return $lista;
}

function lineasEfectivas(string $archivo): int
{
    return count(array_filter(file($archivo) ?: [], static fn (string $l): bool => trim($l) !== ''));
}

$resultados = [];
$anotar = static function (string $id, string $criterio, string $estado, string $detalle) use (&$resultados): void {
    $resultados[] = [$id, $criterio, $estado, $detalle];
};

// E-1 · Paquete congelado en un commit, sin cambios locales en lo que se revisa.
$commit = trim((string) shell_exec('git -C ' . escapeshellarg($micro) . ' rev-parse --short HEAD'));
$sucio = trim((string) shell_exec('git -C ' . escapeshellarg($micro) . ' status --porcelain -- .'));
$anotar('E-1', 'Paquete congelado en un commit y sin cambios locales', $sucio === '' && $commit !== '' ? 'PASA' : 'FALLA',
    $sucio === '' ? "commit $commit" : "hay cambios sin confirmar en micro-his-auditoria:\n" . $sucio);

// E-2 · Suite automatizada sin fallas.
$salida = (string) shell_exec('php ' . escapeshellarg("$micro/tests/ejecutar.php") . ' 2>&1');
preg_match('/Total:\s*(\d+)\s+Pasan:\s*(\d+)\s+Fallan:\s*(\d+)\s+Omitidas:\s*(\d+)/', $salida, $m);
$anotar('E-2', 'Suite automatizada sin fallas', ($m[3] ?? '1') === '0' ? 'PASA' : 'FALLA',
    $m ? "Total {$m[1]} · Pasan {$m[2]} · Fallan {$m[3]} · Omitidas {$m[4]}" : 'no se pudo leer el resumen');

// E-3 · Guardas de contrato y de arquitectura en verde (las que la revisión da por supuestas).
$guardas = ['REC-04', 'API-01', 'API-02', 'API-03', 'ARQ-01', 'ARQ-02', 'ARQ-03', 'ARQ-04', 'ARQ-05', 'ARQ-06', 'ARQ-07', 'ARQ-08'];
$rojas = array_values(array_filter($guardas, static fn (string $g): bool => !preg_match('/\[PASA \]\s+' . preg_quote($g, '/') . '\b/', $salida)));
$anotar('E-3', 'Guardas de contrato (API-01..03, REC-04) y de capas (ARQ-01..08) en verde', $rojas === [] ? 'PASA' : 'FALLA',
    $rojas === [] ? count($guardas) . ' guardas en verde' : 'no pasan: ' . implode(', ', $rojas));

// E-4 · Tamaño de cada sesión dentro del límite.
$paquete = [];
$excedidas = [];
foreach ($sesiones as $nombre => $rutas) {
    $archivos = archivosDe($micro, $rutas);
    $total = array_sum(array_map(static fn (string $a): int => lineasEfectivas("$micro/$a"), $archivos));
    $paquete[$nombre] = [$archivos, $total];
    if ($total > MAX_LINEAS_SESION) {
        $excedidas[] = "$nombre ($total)";
    }
}
$anotar('E-4', 'Cada sesión ≤ ' . MAX_LINEAS_SESION . ' líneas efectivas', $excedidas === [] ? 'PASA' : 'FALLA',
    implode(' · ', array_map(static fn (string $n, array $p): string => explode(' · ', $n)[0] . ': ' . $p[1] . ' líneas en ' . count($p[0]) . ' archivos', array_keys($paquete), $paquete)));

// E-5 · Checklist con 12–15 ítems y formato de hallazgos disponibles.
$items = preg_match_all('/^\| RT-\d{2} \|/m', (string) @file_get_contents("$semana/checklist-revision.md"));
$formato = is_file("$semana/formato-hallazgos.md") && is_file("$semana/plantillas/registro-hallazgos.csv");
$anotar('E-5', 'Checklist de 12–15 ítems y formato de hallazgos publicados', $items >= 12 && $items <= 15 && $formato ? 'PASA' : 'FALLA',
    "$items ítems en el checklist · formato " . ($formato ? 'disponible' : 'FALTA'));

// E-6 · Autorrevisión del autor hecha (el autor no lleva a la reunión lo que él mismo podía encontrar).
$doc = (string) @file_get_contents("$semana/autorrevision-autor.md");
$csv = (string) @file_get_contents("$semana/plantillas/registro-hallazgos.csv");
$nAuto = preg_match_all('/^AR-\d{2},.*,autorrevision\s*$/m', $csv);
$enDoc = preg_match_all('/^### AR-\d{2} /m', $doc);
$anotar('E-6', 'Autorrevisión del autor registrada con el mismo formato', $nAuto > 0 && $nAuto === $enDoc ? 'PASA' : 'FALLA',
    "$nAuto hallazgos del autor en el registro CSV · $enDoc en autorrevision-autor.md");

// E-7 y E-8 · Criterios que verifica el moderador a mano.
$anotar('E-7', 'Material distribuido ≥ 48 h antes de la reunión', 'MANUAL', 'el moderador anota la fecha de envío en el acta');
$anotar('E-8', 'Roles asignados y revisores confirmados', 'MANUAL', 'nombres en el plan §3 y en el acta');

// ---------- salida ----------
echo "Revisión técnica formal Auditoría · criterios de entrada" . PHP_EOL;
echo "Repositorio: " . basename($repo) . " · micro-his-auditoria @ $commit" . PHP_EOL . str_repeat('-', 100) . PHP_EOL;
$fallas = 0;
foreach ($resultados as [$id, $criterio, $estado, $detalle]) {
    $fallas += $estado === 'FALLA' ? 1 : 0;
    printf("%-6s %-5s %s\n       %s\n", $estado, $id, $criterio, str_replace("\n", "\n       ", $detalle));
}
echo str_repeat('-', 100) . PHP_EOL . 'PAQUETE DE REVISIÓN' . PHP_EOL;
foreach ($paquete as $nombre => [$archivos, $total]) {
    echo PHP_EOL . "$nombre — $total líneas efectivas, " . count($archivos) . " archivos" . PHP_EOL;
    foreach ($archivos as $a) {
        printf("  %4d  %s\n", lineasEfectivas("$micro/$a"), $a);
    }
}
echo PHP_EOL . "Criterios automáticos que fallan: $fallas" . PHP_EOL;
exit($fallas > 0 ? 1 : 0);
