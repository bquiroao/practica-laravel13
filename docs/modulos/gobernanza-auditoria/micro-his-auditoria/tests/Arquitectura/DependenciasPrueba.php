<?php

declare(strict_types=1);

use Auditoria\Dominio\Puertos\Bitacora;

/** Lee el código de src/ y devuelve [archivo relativo => contenido], por capa. */
$arqArchivos = static function (string $capa): array {
    $raiz = dirname(__DIR__, 2) . '/src/';
    $archivos = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . $capa, FilesystemIterator::SKIP_DOTS)) as $f) {
        $archivos[substr($f->getPathname(), strlen($raiz))] = (string) file_get_contents($f->getPathname());
    }

    return $archivos;
};

prueba('ARQ-01', 'Regla de dependencias: Presentación → Aplicación → Dominio ← Persistencia (por los «use» de cada archivo)', function () use ($arqArchivos): void {
    $prohibidas = [
        'Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'],
        'Aplicacion' => ['Persistencia', 'Presentacion'],
        'Persistencia' => ['Presentacion'],
        'Presentacion' => ['Persistencia', 'Dominio'],
    ];
    foreach ($prohibidas as $capa => $capasProhibidas) {
        foreach ($arqArchivos($capa) as $archivo => $codigo) {
            foreach ($capasProhibidas as $otra) {
                afirmar(preg_match('/^use Auditoria\\\\' . $otra . '\\\\/m', $codigo) !== 1, "$archivo depende de $otra");
            }
        }
    }
});

prueba('ARQ-02', 'PDO solo existe en Persistencia', function () use ($arqArchivos): void {
    foreach (['Dominio', 'Aplicacion', 'Presentacion'] as $capa) {
        foreach ($arqArchivos($capa) as $archivo => $codigo) {
            afirmar(preg_match('/\bPDO\b/', $codigo) !== 1, "$archivo usa PDO");
        }
    }
});

prueba('ARQ-03', 'Persistencia usa solo sentencias preparadas: sin query()/exec() y sin variables interpoladas en el SQL', function () use ($arqArchivos): void {
    foreach ($arqArchivos('Persistencia') as $archivo => $codigo) {
        afirmar(preg_match('/->(query|exec)\(/', $codigo) !== 1, "$archivo usa query() o exec()");
        afirmar(preg_match('/"[^"]*(SELECT|INSERT|WHERE)[^"]*\$[a-z]/i', $codigo) !== 1, "$archivo interpola una variable en el SQL");
    }
});

prueba('ARQ-04', 'El Dominio no hace E/S ni conoce HTTP: sin superglobales, archivos, salida ni códigos de estado', function () use ($arqArchivos): void {
    foreach ($arqArchivos('Dominio') as $archivo => $codigo) {
        afirmar(preg_match('/\$_(GET|POST|SERVER|REQUEST)|\bfile_(get|put)_contents\(|\becho\b|\bheader\(|http_response_code|\b(201|404|422|503)\b/', $codigo) !== 1,
            "$archivo hace E/S o conoce HTTP");
    }
});

prueba('ARQ-05', 'Inmutabilidad en el código: el puerto no ofrece modificar ni borrar, y src/ no tiene UPDATE ni DELETE sobre audit_logs', function () use ($arqArchivos): void {
    foreach ((new ReflectionClass(Bitacora::class))->getMethods() as $metodo) {
        afirmar(preg_match('/^(actualizar|modificar|eliminar|borrar|update|delete)/i', $metodo->getName()) !== 1, "Bitacora::{$metodo->getName()}");
    }
    foreach (['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion'] as $capa) {
        foreach ($arqArchivos($capa) as $archivo => $codigo) {
            afirmar(preg_match('/UPDATE\s+audit_logs|DELETE\s+FROM\s+audit_logs|TRUNCATE/i', $codigo) !== 1, "$archivo modifica audit_logs");
        }
    }
});
