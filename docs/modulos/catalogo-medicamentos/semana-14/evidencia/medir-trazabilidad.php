<?php
declare(strict_types=1);

// Métrica M-02 del plan SQA: cuántas reglas, criterios y RNF tienen al menos una prueba que los
// nombra. Lee los nombres de las pruebas en micro-his/pruebas (no ejecuta nada).
// Uso: php medir-trazabilidad.php [--evidencia=trazabilidad.txt]

$raiz = __DIR__ . '/../../micro-his/pruebas';
$esperados = [
    'RN' => ['RN-01', 'RN-02', 'RN-03', 'RN-04', 'RN-05', 'RN-06'],
    'CA' => array_map(fn ($n) => sprintf('CA-%02d', $n), range(1, 13)),
    'RNF' => ['RNF-01', 'RNF-02', 'RNF-03', 'RNF-04', 'RNF-05', 'RNF-06', 'RNF-07', 'RNF-08'],
];
$conteo = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $f) {
    if ($f->getExtension() !== 'php' || str_contains($f->getPathname(), '/dobles/')) {
        continue;
    }
    // Solo los nombres de prueba: claves 'texto' => function
    preg_match_all("/'([^']+)'\s*=>\s*(?:function|fn)/", (string) file_get_contents($f->getPathname()), $m);
    foreach ($m[1] as $nombre) {
        preg_match_all('/\b(RNF-\d{2}|RN-\d{2}|CA-\d{2})\b/', $nombre, $ids);
        foreach (array_unique($ids[1]) as $id) {
            $conteo[$id] = ($conteo[$id] ?? 0) + 1;
        }
    }
}
$lineas = ['Trazabilidad requisito -> prueba (M-02) - Catálogo de medicamentos', ''];
$total = $cubiertos = 0;
foreach ($esperados as $grupo => $ids) {
    $faltan = array_values(array_filter($ids, fn ($id) => !isset($conteo[$id])));
    $total += count($ids);
    $cubiertos += count($ids) - count($faltan);
    $lineas[] = sprintf('%-4s %2d de %2d con prueba%s', $grupo, count($ids) - count($faltan), count($ids), $faltan ? ' · sin prueba: ' . implode(', ', $faltan) : '');
}
$lineas[] = '';
$lineas[] = sprintf('Cobertura total: %d de %d (%.0f %%)', $cubiertos, $total, 100 * $cubiertos / $total);
$texto = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $texto;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--evidencia=')) {
        file_put_contents(substr($arg, 12), $texto);
    }
}
