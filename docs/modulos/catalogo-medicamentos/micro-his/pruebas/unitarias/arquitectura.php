<?php
declare(strict_types=1);

// Reglas de dependencia verificadas sobre el código fuente (no por convención).

$archivos = function (string $carpeta): array {
    $lista = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . "/../../src/$carpeta", FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->getExtension() === 'php') {
            $lista[$f->getPathname()] = (string) file_get_contents($f->getPathname());
        }
    }
    return $lista;
};
$sinPatron = function (array $archivos, string $patron, string $que): void {
    foreach ($archivos as $ruta => $codigo) {
        Afirmar::verdadero(preg_match($patron, $codigo) !== 1, "$que en " . basename($ruta));
    }
};

return [
    'Domain no depende de Application, Persistence, Presentation ni PDO' => fn () => $sinPatron($archivos('Domain'),
        '/use Catalogo\\\\(Application|Persistence|Presentation)|\\\\PDO/', 'Domain sin dependencias externas'),
    'Application no depende de Persistence, Presentation ni PDO' => fn () => $sinPatron($archivos('Application'),
        '/use Catalogo\\\\(Persistence|Presentation)|\\\\PDO/', 'Application sin infraestructura'),
    'Presentation no tiene SQL, PDO ni acceso directo a Persistence o al modelo' => fn () => $sinPatron($archivos('Presentation'),
        '/\b(SELECT|INSERT|UPDATE|DELETE)\s|\\\\PDO|->prepare\(|use Catalogo\\\\(Persistence|Domain\\\\Modelo|Domain\\\\Repositorio)/', 'Presentation solo usa casos de uso'),
];
