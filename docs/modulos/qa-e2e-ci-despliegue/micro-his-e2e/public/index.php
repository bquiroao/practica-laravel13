<?php

declare(strict_types=1);

// Front controller: php -S 127.0.0.1:8080 -t public public/index.php
require dirname(__DIR__) . '/bootstrap/contenedor.php';

crearKernel(require dirname(__DIR__) . '/config/config.php')->manejar(\E2e\Presentacion\Http\Solicitud::desdeGlobales())->enviar();
