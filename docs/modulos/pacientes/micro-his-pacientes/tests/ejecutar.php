<?php

declare(strict_types=1);

/*
 * Ejecuta todas las pruebas del Micro-HIS Pacientes.
 *   php tests/ejecutar.php
 * Código de salida: 0 si no hay fallas, 1 si alguna prueba falla.
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';
require __DIR__ . '/Arnes.php';

$grupos = [
    'Dominio' => 'Dominio: reglas de negocio',
    'Aplicacion' => 'Aplicación: camino feliz, reglas y errores de persistencia (dobles en memoria)',
    'Persistencia' => 'Persistencia: PDO con sentencias preparadas (dobles de PDO)',
    'Contratos' => 'Repository: la misma suite de contrato para InMemory y PDO',
    'Presentacion' => 'Presentación: MVC (controlador, formulario, vistas) y HTTP',
    'Arquitectura' => 'Regla de dependencias entre capas',
    'Integracion' => 'Integración con PostgreSQL',
];

foreach ($grupos as $directorio => $titulo) {
    $GLOBALS['grupoActual'] = $titulo;
    foreach (glob(__DIR__ . '/' . $directorio . '/*Prueba.php') ?: [] as $archivo) {
        require $archivo;
    }
}

$conteo = ['PASA' => 0, 'FALLA' => 0, 'OMITE' => 0];
$grupo = null;
echo 'Micro-HIS Pacientes - pruebas (PHP ' . PHP_VERSION . ')' . PHP_EOL;
foreach ($GLOBALS['resultados'] as $r) {
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
