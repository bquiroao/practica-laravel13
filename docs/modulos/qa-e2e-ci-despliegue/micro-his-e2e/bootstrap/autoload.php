<?php

declare(strict_types=1);

// Autoload PSR-4 mínimo, sin Composer: E2e\X\Y => src/X/Y.php
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'E2e\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $archivo = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});
