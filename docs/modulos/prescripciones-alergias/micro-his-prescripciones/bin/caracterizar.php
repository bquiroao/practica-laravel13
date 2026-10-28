<?php

declare(strict_types=1);

/*
 * Semana 7 · Graba el comportamiento observable actual en tests/Caracterizacion/esperado.json.
 * Se ejecutó UNA vez, antes del refactor. No se vuelve a ejecutar para «arreglar» una prueba que falle.
 *   php bin/caracterizar.php
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';
foreach (glob(dirname(__DIR__) . '/tests/Dobles/*.php') ?: [] as $archivo) {
    require_once $archivo;
}
require dirname(__DIR__) . '/tests/Caracterizacion/escenarios.php';

$destino = dirname(__DIR__) . '/tests/Caracterizacion/esperado.json';
file_put_contents($destino, json_encode(caracterizar(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) . "\n");
echo 'Grabados ' . count(json_decode((string) file_get_contents($destino), true)) . " escenarios en tests/Caracterizacion/esperado.json\n";
