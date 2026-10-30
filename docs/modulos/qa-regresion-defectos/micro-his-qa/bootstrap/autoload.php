<?php

declare(strict_types=1);

// Autoload PSR-4 mínimo, sin Composer: Qa\X\Y => src/X/Y.php
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Qa\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $archivo = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});
