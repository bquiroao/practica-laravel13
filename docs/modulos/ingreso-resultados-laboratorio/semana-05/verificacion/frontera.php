<?php

declare(strict_types=1);

// Mide si la frontera del módulo de ingreso de resultados es limpia en el prototipo de la Semana 4.
// Es una condición necesaria para extraer un servicio, no una prueba de que convenga hacerlo.

$root = realpath(__DIR__ . '/../../semana-04/ejemplo');
if ($root === false) {
    fwrite(STDERR, "No se encontró el prototipo de la Semana 4.\n");
    exit(2);
}

$files = static function (string $dir) use ($root): array {
    $out = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir)) as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $out[] = $f->getPathname();
        }
    }
    return $out;
};
$count = static function (array $paths, string $regex): int {
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

$core = array_merge($files('src/Domain'), $files('src/Application'));
$schema = (string) file_get_contents($root . '/database/schema.sql');

$tables = preg_match_all('/CREATE TABLE/i', $schema);
preg_match_all('/REFERENCES\s+(\w+)/i', $schema, $refs);
$externalFks = count(array_filter($refs[1], static fn (string $t): bool => $t !== 'lab_result_versions'));
$selfFks = count($refs[1]) - $externalFks;
$pdoInCore = $count($core, '/\bPDO\b/');
$sqlInCore = $count($core, '/\b(SELECT|INSERT|UPDATE|DELETE)\b/');
$adapters = $count($files('src/Persistence'), '/implements\s+LabResultRepository/');
$foreignImports = $count($core, '/use\s+(?!LabResults\\\\(Domain|Application)\\\\|DateTimeImmutable|InvalidArgumentException)[A-Za-z\\\\]+;/');

echo "Medidas del prototipo (Semana 4):\n";
printf("  Tablas propias del módulo .............. %d\n", $tables);
printf("  Claves foráneas hacia otras tablas ..... %d\n", $externalFks);
printf("  Claves foráneas a la propia tabla ...... %d (versión previa)\n", $selfFks);
printf("  Menciones de PDO en dominio/aplicación . %d\n", $pdoInCore);
printf("  SQL en dominio/aplicación .............. %d\n", $sqlInCore);
printf("  Adaptadores del repositorio ............ %d\n", $adapters);
printf("  Imports de otros módulos en el núcleo .. %d\n\n", $foreignImports);

$check($tables === 1, 'el módulo es dueño de una sola tabla (lab_result_versions)');
$check($externalFks === 0, 'no hay claves foráneas hacia datos de otros módulos');
$check($selfFks === 1, 'la única referencia es de la tabla a sí misma (cadena de versiones)');
$check($pdoInCore === 0, 'el dominio y la aplicación no conocen PDO');
$check($sqlInCore === 0, 'no hay SQL fuera del adaptador de persistencia');
$check($adapters === 2, 'hay dos adaptadores intercambiables del repositorio (PDO e InMemory)');
$check($foreignImports === 0, 'el núcleo no importa clases de otros módulos');
$check(
    str_contains($schema, 'order_item_id') && !preg_match('/patient|paciente|birth|name\b/i', $schema),
    'el ítem de orden es una referencia y no se copian datos del paciente',
);
$check(
    str_contains($schema, 'UNIQUE (order_item_id, version_number)'),
    'la base garantiza una sola versión por número y ítem',
);

echo "\n{$checks} comprobaciones, {$failed} fallidas.\n";
exit($failed === 0 ? 0 : 1);
