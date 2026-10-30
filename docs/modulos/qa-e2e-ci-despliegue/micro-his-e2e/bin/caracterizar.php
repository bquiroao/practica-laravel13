<?php

declare(strict_types=1);

/*
 * Semana 7 · Graba lo que responden hoy los escenarios de caracterización (tests/Caracterizacion/escenarios.php) en
 * tests/Caracterizacion/esperado.json. Se corre UNA vez, antes del refactor; después, CAR-01 compara contra lo grabado.
 *   php bin/caracterizar.php
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';
foreach (glob(dirname(__DIR__) . '/tests/Dobles/*.php') ?: [] as $archivo) {
    require_once $archivo;
}

$grabado = [];
foreach (require dirname(__DIR__) . '/tests/Caracterizacion/escenarios.php' as $nombre => $escenario) {
    $grabado[$nombre] = $escenario();
}
file_put_contents(dirname(__DIR__) . '/tests/Caracterizacion/esperado.json', json_encode($grabado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
printf("%d escenarios grabados en tests/Caracterizacion/esperado.json\n", count($grabado));
