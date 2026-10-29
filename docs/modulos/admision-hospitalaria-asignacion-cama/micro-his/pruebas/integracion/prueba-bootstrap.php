<?php
declare(strict_types=1);

// El Composition Root arma los casos de uso con los adaptadores PDO reales.

use Admision\Presentation\Servicios;
use Pruebas\Afirmar;
use Pruebas\BaseDePrueba;
use Pruebas\Datos;

return [
    'bootstrap.php arma Servicios con PDO y consulta camas reales' => function () {
        $pdo = BaseDePrueba::conexion();
        BaseDePrueba::limpiar($pdo);
        $servicios = (require __DIR__ . '/../../bootstrap.php')($pdo);
        Afirmar::verdadero($servicios instanceof Servicios, 'devuelve Servicios');
        $camas = $servicios->camasDisponibles->ejecutar('MEDICINA_INTERNA', 'GENERAL', false, Datos::contexto('usr-adm-01'));
        Afirmar::igual(['B-201', 'B-202'], array_map(fn ($c) => $c->codigo, $camas));
    },
];
