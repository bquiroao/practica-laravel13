<?php
declare(strict_types=1);

// Cambio práctico de la semana 6: la procedencia del dato «ocupada». A1 está ocupada con dos estancias abiertas (dos pacientes
// en una cama), A2 con una y A3 libre. Se consolida con PDO, desde las tablas reales, antes y después del cambio. Datos ficticios.
require __DIR__ . '/../../micro-his-dashboard-ocupacion/bootstrap.php';

use MicroHis\DashboardOcupacion\Aplicacion\Reloj;
use MicroHis\DashboardOcupacion\Persistencia\RepositorioPdo;
use function MicroHis\DashboardOcupacion\crearControlador;

$db = new PDO('sqlite::memory:');
$db->exec(file_get_contents(__DIR__ . '/../../micro-his-dashboard-ocupacion/database/schema.sql'));
$db->exec("INSERT INTO wards VALUES (1, 'A', 'active');
           INSERT INTO beds VALUES (1, 1, 'A1', 'occupied'), (2, 1, 'A2', 'occupied'), (3, 1, 'A3', 'available');
           INSERT INTO bed_stays (bed_id, ended_at) VALUES (1, NULL), (1, NULL), (2, NULL)");
$app = crearControlador(new RepositorioPdo($db), new class implements Reloj { public function ahora(): int { return 1000; } });
$app->atender('consolidar', []);
$a = $app->atender('consultar', [])['salas']['A'];
echo "sala A: ocupación {$a['ocupacion']} %, ocupadas {$a['occupied']}, disponibles {$a['available']}; inconsistentes: [" . implode(', ', $a['inconsistentes']) . "]\n";
