<?php
declare(strict_types=1);

// Front controller MVC. Servidor de desarrollo: php -S 127.0.0.1:8085 -t public
// CATALOGO_ALMACEN=memoria usa el adaptador InMemory (sin base; los datos se reinician en cada petición).

use Catalogo\Domain\Errores\ErrorPersistencia;
use Catalogo\Presentation\Web\ControladorMedicamentos;
use Catalogo\Presentation\Web\Csrf;
use Catalogo\Presentation\Web\Peticion;
use Catalogo\Presentation\Web\Respuesta;
use Catalogo\Presentation\Web\RutasWeb;
use Catalogo\Presentation\Web\Vista;

require __DIR__ . '/../bootstrap.php';

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
$config = require __DIR__ . '/../config.php';
$vista = new Vista();

try {
    $app = crearAplicacion($config, null, getenv('CATALOGO_ALMACEN') ?: 'pdo');
} catch (ErrorPersistencia $e) {
    error_log('[catalogo] ' . ($e->getPrevious()?->getMessage() ?? $e->getMessage()));
    (new Respuesta(503, $vista->mostrar('error', ['mensaje' => $e->getMessage()], 'Servicio no disponible')))->enviar();
    exit;
}

// Semana 4: usuario de demostración fijo (QF del Hospital Norte). La autenticación llega en la semana 5 (API).
$controlador = new ControladorMedicamentos($app, $vista, Csrf::desdeSesion(), $config['web']['hospital'], $config['web']['usuario']);
RutasWeb::crear($controlador)->despachar(Peticion::desdeGlobales(), $controlador->error(...))->enviar();
