<?php
declare(strict_types=1);

// Autorrevisión del autor (criterio de entrada E-03): corre las pruebas del Micro-HIS y reproduce cada hallazgo
// sobre el código tal como está. Si alguien corrige un hallazgo, su línea pasa a «no reproducido». Datos ficticios.
$raiz = __DIR__ . '/../../micro-his-catalogo-camas';
require $raiz . '/bootstrap.php';

use MicroHis\Catalogo\Persistencia\RepositorioPdo;
use MicroHis\Catalogo\Presentacion\{ControladorCatalogo, VistaJson};
use function MicroHis\Catalogo\crearControlador;

function linea(string $id, bool $reproducido, string $detalle): void { printf("%-5s %-15s %s\n", $id, $reproducido ? 'reproducido' : 'no reproducido', $detalle); }
function columnas(PDO $db, string $tabla): array { return array_column($db->query("PRAGMA table_info($tabla)")->fetchAll(), 'name'); }

exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$raiz/pruebas/pruebas.php"), $salida, $codigo);
printf("ENTRADA pruebas del Micro-HIS: %s (%s)\n\n", $codigo === 0 ? 'en verde' : 'FALLAN', end($salida));

$db = new PDO('sqlite::memory:');
$db->exec(file_get_contents("$raiz/database/schema.sql"));
$app = crearControlador(new RepositorioPdo($db));

// H-01: CU-05 / R-07. La única entrada del controlador no recibe usuario, permiso ni hospital, y no existe E1.
$parametros = array_map(fn ($p) => $p->getName(), (new ReflectionMethod(ControladorCatalogo::class, 'atender'))->getParameters());
$http = (new ReflectionClassConstant(VistaJson::class, 'HTTP'))->getValue();   // desde la semana 4, la vista es dueña de los códigos
linea('H-01', !isset($http['E1']), 'entrada atender(' . implode(', ', $parametros) . '); códigos: ' . implode(' ', array_keys($http)));

// H-02: R-09. El historial guarda fecha y motivo, pero no quién hizo el cambio.
$historial = columnas($db, 'bed_status_history');
linea('H-02', array_intersect(['user_id', 'changed_by'], $historial) === [], 'bed_status_history: ' . implode(', ', $historial));

// H-03: R-01. El código se valida con trim(), pero se guarda sin normalizar: «A» y « A» conviven.
$a = $app->atender('registrarSala', ['codigo' => 'A', 'nombre' => 'Medicina interna'])['status'];
$b = $app->atender('registrarSala', ['codigo' => ' A', 'nombre' => 'Medicina interna'])['status'];
linea('H-03', $b === 201, "registrar «A» = $a; registrar « A» = $b (se esperaba 409 E3)");

// H-04: CU-01 pide ubicación y CU-02 tipo de cama; el esquema no los tiene.
$faltan = array_merge(array_diff(['location'], columnas($db, 'wards')), array_diff(['type'], columnas($db, 'beds')));
linea('H-04', $faltan !== [], 'faltan en el esquema: ' . ($faltan === [] ? 'nada' : implode(', ', $faltan)));

// H-05: CU-03 dice que admisión marca «ocupada», pero ninguna transición manual llega a ese estado.
$app->atender('registrarCama', ['sala' => 'A', 'codigo' => '101']);
$r = $app->atender('cambiarEstado', ['sala' => 'A', 'cama' => '101', 'estado' => 'occupied', 'motivo' => 'ingreso']);
linea('H-05', $r['status'] !== 200, "disponible -> ocupada = {$r['status']} " . ($r['error'] ?? ''));
