<?php

declare(strict_types=1);

// Carga los archivos del ejemplo: el dominio primero, los adaptadores al final (dependen del dominio, nunca al revés).
foreach (['Dominio/Modelo', 'Dominio/Puertos', 'Dominio/QualityGate', 'Aplicacion/EvaluarGate',
    'Adaptadores/ReporteJUnit', 'Adaptadores/ReporteJson', 'Adaptadores/Infraestructura'] as $archivo) {
    require_once __DIR__ . "/src/$archivo.php";
}
