<?php

declare(strict_types=1);

// Autoload PSR-4 mínimo: NotasSoap\Capa\Clase => src/Capa/Clase.php ; NotasSoap\Pruebas\X => tests/X.php
spl_autoload_register(static function (string $clase): void {
    $mapa = ['NotasSoap\\Pruebas\\' => dirname(__DIR__) . '/tests/', 'NotasSoap\\' => dirname(__DIR__) . '/src/'];
    foreach ($mapa as $prefijo => $base) {
        if (str_starts_with($clase, $prefijo)) {
            $archivo = $base . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
            if (is_file($archivo)) {
                require $archivo;
            }

            return;
        }
    }
});
