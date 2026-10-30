<?php
// Punto de entrada por consola. --ahora fija el reloj (para demostrar el escalamiento sin esperar).
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use AlertasCriticas\Domain\ErrorPersistenciaException;
use AlertasCriticas\Persistence\RelojFijo;
use AlertasCriticas\Presentation\ConsolaAlertas;

$argumentos = array_slice($argv, 1);
$reemplazos = [];
foreach ($argumentos as $i => $arg) {
    if (str_starts_with($arg, '--ahora=')) {
        $reemplazos['reloj'] = new RelojFijo(substr($arg, 8));
        unset($argumentos[$i]);
    }
}

try {
    $casos = contenedorAlertas($reemplazos);
} catch (ErrorPersistenciaException $e) {
    fwrite(STDERR, 'Error de persistencia: ' . $e->getMessage() . PHP_EOL . 'Revise: docker compose ps' . PHP_EOL);
    exit(ConsolaAlertas::ERROR_PERSISTENCIA);
}
exit((new ConsolaAlertas($casos))->ejecutar(array_values($argumentos)));
