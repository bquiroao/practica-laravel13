<?php

declare(strict_types=1);

/*
 * Ejecuta todas las pruebas del Micro-HIS Auditoría.
 *   php tests/ejecutar.php
 *   php tests/ejecutar.php --solo=UNI,CB,CN   (muestra y cuenta solo esos prefijos; semana 15)
 *   php tests/ejecutar.php --defectos         (agrega las pruebas de defectos ABIERTOS, que fallan hasta corregirse)
 * Código de salida: 0 si no hay fallas, 1 si alguna prueba falla.
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';
require __DIR__ . '/Arnes.php';

$grupos = [
    'Dominio' => 'Dominio: reglas del movimiento, filtros y sello',
    'Aplicacion' => 'Aplicación: camino feliz, reglas de dominio y errores de persistencia (dobles)',
    'Persistencia' => 'Persistencia: PDO con sentencias preparadas (dobles de PDO)',
    'Contratos' => 'Repository: la misma suite de contrato para InMemory y PDO',
    'Presentacion' => 'Presentación: MVC, HTTP y observabilidad',
    'Cliente' => 'Cliente-servidor: librería cliente, bandeja local y resiliencia',
    'Arquitectura' => 'Regla de dependencias entre capas',
    'Integracion' => 'Integración con PostgreSQL real',
];
$argumentos = array_slice($argv ?? [], 1);
if (in_array('--defectos', $argumentos, true)) {
    $grupos['Defectos'] = 'Defectos abiertos (fallan hasta que se corrijan)';
}
$prefijos = [];
foreach ($argumentos as $argumento) {
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
echo 'Micro-HIS Auditoría - pruebas (PHP ' . PHP_VERSION . ')' . PHP_EOL;
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
