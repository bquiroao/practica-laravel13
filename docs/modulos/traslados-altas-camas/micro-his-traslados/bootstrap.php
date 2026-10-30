<?php
declare(strict_types=1);

// Carga por capas, de adentro hacia afuera: Dominio → Aplicación → Persistencia → Presentación (sin Composer).
foreach (['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion'] as $capa) {
    foreach (glob(__DIR__ . "/src/$capa/*.php") as $archivo) { require_once $archivo; }
}
