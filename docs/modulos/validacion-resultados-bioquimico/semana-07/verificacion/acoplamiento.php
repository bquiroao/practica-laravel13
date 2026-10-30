<?php

declare(strict_types=1);

$base = __DIR__ . '/../../semana-02/ejemplo-dip';
$strip = static fn (string $code): string => (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $code);

$subjects = [
    'ANTES  CoupledLabResultReview' => $strip((string) file_get_contents($base . '/antes/CoupledLabResultReview.php')),
    'DESPUES ReviewPendingLabResult' => (static function () use ($base, $strip): string {
        $code = $strip((string) file_get_contents($base . '/despues/ReviewWorkflow.php'));
        preg_match('/final class ReviewPendingLabResult.*\z/s', $code, $m);

        return $m[0] ?? '';
    })(),
];

$metrics = [
    'Usa PDO' => '/\bPDO\b/',
    'Contiene SQL' => '/\b(SELECT|UPDATE|INSERT)\b/',
    'Maneja transacción' => '/beginTransaction|commit\(|rollBack/',
    'Lee el reloj del sistema' => '/\bdate\(|new\s+DateTimeImmutable\(|\btime\(/',
    'Compara el rol con un texto fijo' => "/'bioquimico'/",
    'Devuelve el resultado como texto libre' => "/return\s+'[A-Z_]+'/",
];

$failures = 0;
$results = [];
foreach ($subjects as $name => $code) {
    if ($code === '') {
        fwrite(STDERR, "No se pudo aislar {$name}" . PHP_EOL);
        exit(1);
    }
    foreach ($metrics as $label => $pattern) {
        $results[$label][$name] = preg_match_all($pattern, $code);
    }
    $results['Líneas de código'][$name] = count(array_filter(array_map('trim', explode("\n", $code))));
}

printf("%-42s %-32s %s\n", 'Medida', array_key_first($subjects), array_key_last($subjects));
echo str_repeat('-', 96) . PHP_EOL;
$names = array_keys($subjects);
foreach ($results as $label => $row) {
    printf("%-42s %-32d %d\n", $label, $row[$names[0]], $row[$names[1]]);
}
echo str_repeat('-', 96) . PHP_EOL;

foreach (array_keys($metrics) as $label) {
    $ok = $results[$label][$names[0]] > 0 && $results[$label][$names[1]] === 0;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . "{$label}: presente antes y ausente después" . PHP_EOL;
    $failures += $ok ? 0 : 1;
}

preg_match('/function __construct\((.*?)\)\s*\{/s', $subjects[$names[1]], $m);
$ctor = $m[1] ?? '';
$ok = preg_match_all('/private\s+(ReviewerAuthorization|Clock|LabResultDecisionRepository)\s+\$/', $ctor) === 3;
echo ($ok ? 'PASS: ' : 'FAIL: ') . 'el constructor recibe solo los 3 contratos esperados' . PHP_EOL;
$failures += $ok ? 0 : 1;

echo $failures === 0 ? 'OK: comparación verificada.' . PHP_EOL : "FALLAS: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
