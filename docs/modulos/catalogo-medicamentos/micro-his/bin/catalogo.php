<?php
declare(strict_types=1);

// Entrada de consola del Micro-HIS (capa Presentation). Ejemplo: php bin/catalogo.php buscar amoxi

use Catalogo\Domain\Errores\ErrorPersistencia;
use Catalogo\Presentation\Consola\ConsolaCatalogo;

require __DIR__ . '/../bootstrap.php';

try {
    $app = crearAplicacion();
} catch (ErrorPersistencia $e) {
    fwrite(STDERR, '[503] ' . $e->getMessage() . ' ¿Está levantada la base (docker compose ps)?' . PHP_EOL);
    exit(3);
}
exit((new ConsolaCatalogo($app))->ejecutar($argv));
