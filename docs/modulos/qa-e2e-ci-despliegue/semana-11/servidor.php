<?php

declare(strict_types=1);

/*
 * Semana 11 · Sirve el prototipo navegable y la API REAL desde el mismo origen (sin CORS ni API simulada):
 *   E2E_PERSISTENCIA=pdo E2E_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_qa" \
 *     php -S 127.0.0.1:8092 -t prototipo servidor.php
 * /api/... va al front controller de micro-his-e2e; cualquier otra ruta, a los archivos de prototipo/.
 */

if (str_starts_with((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/api/')) {
    require __DIR__ . '/../micro-his-e2e/public/index.php';

    return true;
}

return false;
