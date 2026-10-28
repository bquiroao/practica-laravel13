<?php
declare(strict_types=1);

// Uso: php cli.php alta datos/hem-001.json | vigentes [texto] [AAAA-MM-DD] | detalle HEM-001 [AAAA-MM-DD]
require __DIR__ . '/bootstrap.php';

exit(crearConsola()->ejecutar($argv));
