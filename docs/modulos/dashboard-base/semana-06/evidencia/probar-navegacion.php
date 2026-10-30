<?php
declare(strict_types=1);

// Cambio práctico de la semana 6: CU-03 «navegar a un módulo», con E6 si el rol no lo permite (R-02). Pasa por las rutas
// reales del front controller; el mismo guion se corrió antes y después del cambio. Datos ficticios.
require __DIR__ . '/../../micro-his-dashboard-base/bootstrap.php';

use MicroHis\DashboardBase\Aplicacion\Reloj;
use MicroHis\DashboardBase\Persistencia\RepositorioEnMemoria;
use MicroHis\DashboardBase\Presentacion\Rutas;
use function MicroHis\DashboardBase\crearControlador;

$reloj = new class implements Reloj { public function hoy(): string { return '2026-10-29'; } };
$app = crearControlador(new RepositorioEnMemoria([], [], []), $reloj);
$recepcion = ['hospital' => 'h1', 'permisos' => ['citas.ver']];   // el rol ve Citas, no Camas
foreach (['/dashboard/navegacion/citas' => $recepcion, '/dashboard/navegacion/camas' => $recepcion,
          '/dashboard/navegacion/usuarios' => $recepcion, '/dashboard/navegacion/citas ' => ['hospital' => ''] + $recepcion] as $ruta => $sesion) {
    [$accion, $parametros] = Rutas::resolver('GET', trim($ruta));
    $r = $app->atender($accion, $parametros + $sesion);
    printf("GET %s%s -> %s%s\n", trim($ruta), $sesion['hospital'] === '' ? ' (sin hospital)' : '', rtrim("{$r['status']} " . ($r['error'] ?? '')), isset($r['titulo']) ? " «{$r['titulo']}»" : '');
}
