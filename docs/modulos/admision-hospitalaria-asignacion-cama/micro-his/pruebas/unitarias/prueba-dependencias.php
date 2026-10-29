<?php
declare(strict_types=1);

// Prueba de arquitectura: cada capa solo importa lo que el diagrama de dependencias permite.

use Pruebas\Afirmar;

$prohibidas = [
    'Domain' => ['Application', 'Persistence', 'Presentation'],
    'Application' => ['Persistence', 'Presentation'],
    'Persistence' => ['Application', 'Presentation'],
];
$violaciones = function (string $capa, array $noPermitidas): array {
    $encontradas = [];
    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../src/' . $capa));
    foreach ($archivos as $archivo) {
        if ($archivo->getExtension() !== 'php') {
            continue;
        }
        $codigo = (string) file_get_contents($archivo->getPathname());
        foreach ($noPermitidas as $otra) {
            if (str_contains($codigo, 'Admision\\' . $otra . '\\')) {
                $encontradas[] = $archivo->getFilename() . ' -> ' . $otra;
            }
        }
    }
    return $encontradas;
};

$pruebas = [];
foreach ($prohibidas as $capa => $noPermitidas) {
    $pruebas["{$capa} no depende de " . implode(', ', $noPermitidas)] = function () use ($violaciones, $capa, $noPermitidas) {
        Afirmar::igual([], $violaciones($capa, $noPermitidas));
    };
}
$pruebas['Solo Persistence usa PDO y SQL'] = function () {
    foreach (['Domain', 'Application', 'Presentation'] as $capa) {
        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../src/' . $capa));
        foreach ($archivos as $archivo) {
            if ($archivo->getExtension() === 'php') {
                $codigo = (string) file_get_contents($archivo->getPathname());
                Afirmar::verdadero(!preg_match('/\bPDO\b|\bSELECT\b|\bINSERT\b/', $codigo), "{$capa}/{$archivo->getFilename()} no debe usar PDO ni SQL");
            }
        }
    }
};
return $pruebas;
