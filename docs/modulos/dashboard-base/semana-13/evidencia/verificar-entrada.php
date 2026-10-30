<?php
declare(strict_types=1);

// Autorrevisión del autor (criterio de entrada E-03): corre las pruebas del Micro-HIS y reproduce cada hallazgo
// sobre el código tal como está. Si alguien corrige un hallazgo, su línea pasa a «no reproducido». Datos ficticios.
$raiz = __DIR__ . '/../../micro-his-dashboard-base';
require $raiz . '/bootstrap.php';

use MicroHis\DashboardBase\Aplicacion\Reloj;
use MicroHis\DashboardBase\Persistencia\RepositorioPdo;
use MicroHis\DashboardBase\Presentacion\VistaJson;
use function MicroHis\DashboardBase\crearControlador;

function linea(string $id, bool $reproducido, string $detalle): void { printf("%-5s %-15s %s\n", $id, $reproducido ? 'reproducido' : 'no reproducido', $detalle); }
$resultado = fn (array $r) => rtrim("{$r['status']} " . ($r['error'] ?? ''));

exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$raiz/pruebas/pruebas.php"), $salida, $codigo);
printf("ENTRADA pruebas del Micro-HIS: %s (%s)\n\n", $codigo === 0 ? 'en verde' : 'FALLAN', end($salida));

$db = new PDO('sqlite::memory:');
$db->exec(file_get_contents("$raiz/database/schema.sql"));
$db->exec("INSERT INTO local_patients (status) VALUES ('active')");
$reloj = new class implements Reloj { public function hoy(): string { return '2026-10-26'; } };
$app = crearControlador(new RepositorioPdo($db), $reloj);
$todos = ['hospital' => 'H1', 'permisos' => ['pacientes.ver', 'citas.ver', 'camas.ver']];
$http = (new ReflectionClassConstant(VistaJson::class, 'HTTP'))->getValue();   // desde la semana 4, la vista es dueña de los códigos

// H-01: R-02 / CU-05. Hospital y permisos llegan en la petición: cualquier texto sirve de hospital y cualquier permiso se acepta.
$r = $app->atender('tarjeta', ['id' => 'pacientes', 'hospital' => 'hospital-ajeno', 'permisos' => ['pacientes.ver']]);
linea('H-01', $r['status'] === 200, "hospital = «hospital-ajeno» con permisos escritos por el cliente = {$resultado($r)} «{$r['texto']}»; E3 en el mapa HTTP: " . (isset($http['E3']) ? 'sí' : 'no'));

// H-02: CU-03 / CU-05. No hay operación que valide una ruta escrita a mano, ni código E6.
$r = $app->atender('navegar', ['ruta' => '/usuarios'] + $todos);
linea('H-02', !isset($http['E6']), "navegar a /usuarios = {$resultado($r)}; códigos: " . implode(' ', array_keys($http)));

// H-03: una tarjeta que no existe responde 403 E4, y R-07 hace que el cliente la oculte como si fuera falta de permiso.
$r = $app->atender('tarjeta', ['id' => 'tarjeta-inexistente'] + $todos);
linea('H-03', $resultado($r) === '403 E4', "tarjeta «tarjeta-inexistente» = {$resultado($r)} (se esperaba 404)");

// H-04: «citas de hoy» usa date() sobre el texto guardado: una cita de las 20:00 del 26 en Guatemala, guardada en UTC, cae el 27.
$db->exec("INSERT INTO appointments (starts_at, status) VALUES ('2026-10-27T02:00:00Z', 'scheduled')");
$r = $app->atender('tarjeta', ['id' => 'citas_hoy'] + $todos);
linea('H-04', $r['estado'] === 'vacio', "cita 2026-10-27T02:00:00Z (20:00 del 26, UTC-6) con hoy = 2026-10-26: «{$r['texto']}»");

// H-05: con un solo registro el texto queda en plural: «1 pacientes activos».
$r = $app->atender('tarjeta', ['id' => 'pacientes'] + $todos);
linea('H-05', str_starts_with($r['texto'], '1 pacientes'), "1 paciente activo en la base: «{$r['texto']}»");
