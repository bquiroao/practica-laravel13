<?php

declare(strict_types=1);

// Mide si la frontera del módulo de recepción es limpia en el prototipo de la Semana 3.
// Es una condición necesaria para extraer un servicio, no una prueba de que convenga hacerlo.

$root = realpath(__DIR__ . '/../../semana-03/ejemplo');
if ($root === false) {
    fwrite(STDERR, "No se encontró el prototipo de la Semana 3.\n");
    exit(2);
}

$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);
$files = static function (string $dir) use ($root): array {
    $out = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir)) as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $out[] = $f->getPathname();
        }
    }
    return $out;
};
$mentions = static function (array $paths, string $regex): int {
    $n = 0;
    foreach ($paths as $p) {
        $n += preg_match_all($regex, (string) file_get_contents($p));
    }
    return $n;
};

$checks = 0;
$failed = 0;
$check = static function (bool $ok, string $message) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    echo ($ok ? 'PASS' : 'FAIL') . ": {$message}\n";
};

$domain = $files('src/Domain');
$application = $files('src/Application');
$schema = $read('database/schema.sql');

$tables = preg_match_all('/CREATE TABLE/i', $schema);
$foreignKeys = preg_match_all('/REFERENCES|FOREIGN KEY/i', $schema);
$pdoInCore = $mentions(array_merge($domain, $application), '/\bPDO\b/');
$sqlInCore = $mentions(array_merge($domain, $application), '/\b(SELECT|INSERT|UPDATE|DELETE)\b/');
$adapters = $mentions($files('src/Persistence'), '/implements\s+SpecimenRepository/');
$otherModules = $mentions(array_merge($domain, $application), '/use\s+(?!MicroHis\\\\(Domain|Application)\\\\|DateTimeImmutable|InvalidArgumentException)[A-Za-z\\\\]+;/');

echo "Medidas del prototipo (Semana 3):\n";
printf("  Tablas propias del módulo ............ %d\n", $tables);
printf("  Claves foráneas a otros módulos ...... %d\n", $foreignKeys);
printf("  Menciones de PDO en dominio/aplicación %d\n", $pdoInCore);
printf("  SQL en dominio/aplicación ............ %d\n", $sqlInCore);
printf("  Adaptadores del repositorio .......... %d\n", $adapters);
printf("  Imports de otros módulos en el núcleo  %d\n\n", $otherModules);

$check($tables === 1, 'el módulo es dueño de una sola tabla (specimen_receptions)');
$check($foreignKeys === 0, 'no hay claves foráneas hacia datos de otros módulos');
$check($pdoInCore === 0, 'el dominio y la aplicación no conocen PDO');
$check($sqlInCore === 0, 'no hay SQL fuera del adaptador de persistencia');
$check($adapters === 1, 'existe un único adaptador del repositorio, intercambiable');
$check($otherModules === 0, 'el núcleo no importa clases de otros módulos');
$check(
    str_contains($schema, 'order_reference') && str_contains($schema, 'patient_reference'),
    'la orden y el paciente se guardan como referencias, no como copias de sus datos',
);
$check(
    !str_contains(strtolower($schema), 'patient_name') && !str_contains(strtolower($schema), 'birth'),
    'no se duplican datos personales del paciente en la tabla',
);

echo "\n{$checks} comprobaciones, {$failed} fallidas.\n";
exit($failed === 0 ? 0 : 1);
