<?php

declare(strict_types=1);

/*
 * Carga en PostgreSQL los pacientes ficticios de database/datos-demo.php A TRAVÉS de los
 * casos de uso, para que el documento quede cifrado y cada registro deje su evento.
 *   php bin/cargar-demo.php
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';

$config = require dirname(__DIR__) . '/config/config.php';
$casos = construirCasosDeUso(crearAdaptadores(['persistencia' => 'pdo'] + $config),
    new Pacientes\Persistencia\Configuracion\PoliticaAccesoEstatica($config['permisos']),
    new Pacientes\Persistencia\Configuracion\RelojSistema());

foreach (sembrarDemo($casos) as [$hospital, $datos, $r]) {
    echo sprintf('%-13s %-28s %s', $hospital, $datos['nombres'] . ' ' . $datos['apellidos'],
        $r->exitoso() ? $r->datos['codigo'] : $r->estado . ' ' . $r->error) . PHP_EOL;
}
