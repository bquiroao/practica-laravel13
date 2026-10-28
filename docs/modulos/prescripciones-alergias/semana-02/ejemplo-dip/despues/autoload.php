<?php

declare(strict_types=1);

// Autoload PSR-4 mínimo: Prescripciones\X\Y => src/X/Y.php
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Prescripciones\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $archivo = __DIR__ . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});
