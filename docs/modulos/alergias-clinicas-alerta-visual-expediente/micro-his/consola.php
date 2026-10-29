<?php
declare(strict_types=1);

// php consola.php alerta --paciente=PAC-00451
// php consola.php registrar --paciente=PAC-00451 --alergeno=Penicilina --severidad=life_threatening
//     --reaccion="Edema de glotis" --inicio=2019-03-02 --usuario=7a1e0000-0000-4000-8000-0000000000a1
require __DIR__ . '/bootstrap.php';

use Alergias\Dominio\ErrorPersistencia;
use Alergias\Presentacion\Consola\ComandoConsola;

// getopt() se detiene en el comando; por eso las opciones --clave=valor se leen a mano.
$opciones = [];
foreach (array_slice($argv, 2) as $argumento) {
    if (preg_match('/^--([a-z]+)=(.*)$/s', $argumento, $m)) {
        $opciones[$m[1]] = $m[2];
    }
}
try {
    $casos = armarCasosDeUso();
} catch (ErrorPersistencia $e) {
    echo "SIN SERVICIO {$e->getMessage()}" . PHP_EOL;
    exit(4);
}
exit((new ComandoConsola($casos['registrar'], $casos['consultar']))->ejecutar($argv[1] ?? '', $opciones));
