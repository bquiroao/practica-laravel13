<?php

declare(strict_types=1);

/*
 * Ejecuta todas las pruebas del Micro-HIS E2E.
 *   php tests/ejecutar.php
 *   php tests/ejecutar.php --solo=APP,INT     (muestra y cuenta solo esos prefijos)
 * Con E2E_DB_DSN definido corren también las de integración con PostgreSQL; sin él, se omiten.
 * Código de salida: 0 si no hay fallas, 1 si alguna prueba falla.
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';
require __DIR__ . '/Arnes.php';
foreach (glob(__DIR__ . '/Dobles/*.php') ?: [] as $archivo) {
    require_once $archivo;
}

$grupos = [
    'Dominio' => 'Dominio: escenarios, versiones y plan, ejecuciones, resultados y quality gate',
    'Aplicacion' => 'Aplicación: camino feliz, reglas y errores de persistencia (dobles)',
    'Persistencia' => 'Persistencia: PDO con sentencias preparadas y almacén de evidencias',
    'Contratos' => 'Repository: la misma suite de contrato para los adaptadores InMemory y PDO',
    'Mvc' => 'MVC: formularios, controladores y vistas',
    'Caracterizacion' => 'Caracterización (semana 7): el refactor de «reportar» y «desplegar» no cambia el comportamiento',
    'Cambio' => 'Cambio práctico de la semana 6: despliegue reproducible (CU-11, R-11)',
    'Presentacion' => 'Presentación: HTTP',
    'Api' => 'Contrato API (OpenAPI) = código',
    'Cliente' => 'Cliente-servidor: reportador del pipeline con reintentos y bandeja, y observabilidad',
    'Arquitectura' => 'Regla de dependencias entre capas',
    'Integracion' => 'Integración con PostgreSQL real',
];
$prefijos = [];
foreach (array_slice($argv ?? [], 1) as $argumento) {
    if (str_starts_with($argumento, '--solo=')) {
        $prefijos = array_filter(explode(',', substr($argumento, 7)));
    }
}

foreach ($grupos as $directorio => $titulo) {
    $GLOBALS['grupoActual'] = $titulo;
    foreach (glob(__DIR__ . '/' . $directorio . '/*Prueba.php') ?: [] as $archivo) {
        require $archivo;
    }
}

$visibles = array_values(array_filter($GLOBALS['resultados'], static fn (array $r): bool => $prefijos === []
    || array_filter($prefijos, static fn (string $p): bool => str_starts_with($r['id'], $p)) !== []));
printf("Micro-HIS QA E2E, CI y despliegue - pruebas (PHP %s)\n", PHP_VERSION);
$grupo = null;
foreach ($visibles as $r) {
    if ($r['grupo'] !== $grupo) {
        $grupo = $r['grupo'];
        echo "\n== $grupo ==\n";
    }
    printf("[%-5s] %-8s %s\n", $r['estado'], $r['id'], $r['descripcion']);
    if ($r['detalle'] !== null) {
        echo '         -> ' . $r['detalle'] . "\n";
    }
}
$conteo = array_count_values(array_column($visibles, 'estado'));
printf("\nTotal: %d  Pasan: %d  Fallan: %d  Omitidas: %d\n", count($visibles), $conteo['PASA'] ?? 0, $conteo['FALLA'] ?? 0, $conteo['OMITE'] ?? 0);
exit(($conteo['FALLA'] ?? 0) > 0 ? 1 : 0);
