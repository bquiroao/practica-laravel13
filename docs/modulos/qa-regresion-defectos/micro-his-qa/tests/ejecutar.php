<?php

declare(strict_types=1);

/*
 * Ejecuta todas las pruebas del Micro-HIS QA.
 *   php tests/ejecutar.php
 *   php tests/ejecutar.php --solo=APP,INT     (muestra y cuenta solo esos prefijos)
 * Con QA_DB_DSN definido corren también las de integración con PostgreSQL; sin él, se omiten.
 * Código de salida: 0 si no hay fallas, 1 si alguna prueba falla.
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';
require __DIR__ . '/Arnes.php';
foreach (glob(__DIR__ . '/Dobles/*.php') ?: [] as $archivo) {
    require_once $archivo;
}

$grupos = [
    'Dominio' => 'Dominio: reglas de casos, campañas, evidencias y defectos',
    'Aplicacion' => 'Aplicación: camino feliz, reglas de dominio y errores de persistencia (dobles)',
    'Persistencia' => 'Persistencia: PDO con sentencias preparadas (dobles de PDO) y almacén de evidencias',
    'Contratos' => 'Repository: la misma suite de contrato para los adaptadores InMemory y PDO',
    'Mvc' => 'MVC: formularios, controladores y vistas',
    'Caracterizacion' => 'Caracterización (semana 7): el refactor de «registrar resultado» no cambia el comportamiento',
    'Cambio' => 'Cambio práctico de la semana 6 (R-15): MAYOR abierto en caso crítico bloquea el GO',
    'Presentacion' => 'Presentación: HTTP y observabilidad',
    'Api' => 'Contrato API (OpenAPI) = código',
    'Cliente' => 'Cliente-servidor: cliente con bandeja, almacén remoto y circuito',
    'Arquitectura' => 'Regla de dependencias entre capas',
    'Integracion' => 'Integración con PostgreSQL real',
    'Semana15' => 'Semana 15: unitarias, caja blanca y caja negra trazadas a RF/RNF (y los hallazgos de la revisión)',
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

$conteo = ['PASA' => 0, 'FALLA' => 0, 'OMITE' => 0];
$grupo = null;
echo 'Micro-HIS QA funcional y regresión - pruebas (PHP ' . PHP_VERSION . ')' . PHP_EOL;
foreach ($GLOBALS['resultados'] as $r) {
    if ($prefijos !== [] && !in_array(explode('-', $r['id'])[0], $prefijos, true)) {
        continue;
    }
    if ($r['grupo'] !== $grupo) {
        $grupo = $r['grupo'];
        echo PHP_EOL . '== ' . $grupo . ' ==' . PHP_EOL;
    }
    $conteo[$r['estado']]++;
    echo sprintf('[%s] %-8s %s', str_pad($r['estado'], 5), $r['id'], $r['descripcion']) . PHP_EOL;
    if ($r['detalle'] !== null) {
        echo '         -> ' . $r['detalle'] . PHP_EOL;
    }
}
echo PHP_EOL . sprintf('Total: %d  Pasan: %d  Fallan: %d  Omitidas: %d', array_sum($conteo), $conteo['PASA'], $conteo['FALLA'], $conteo['OMITE']) . PHP_EOL;

exit($conteo['FALLA'] === 0 ? 0 : 1);
