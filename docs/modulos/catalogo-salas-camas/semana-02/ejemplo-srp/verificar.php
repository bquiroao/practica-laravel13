<?php
declare(strict_types=1);

// Evidencia: (1) paridad de comportamiento antes/después en 11 escenarios (CA-01..CA-08),
// (2) la política de estados se prueba sola, sin repositorio ni auditoría.
require __DIR__ . '/antes.php';
require __DIR__ . '/despues.php';

$antes = new CatalogoCamasAntes();
$repo = new RepositorioEnMemoria();
$auditoria = new AuditoriaEnMemoria();
$servicio = new ServicioCatalogo($repo, new PoliticaEstadosCama(), $auditoria);
$http = new RespuestaHttp();

// [CA, descripción, llamada antes, llamada después, status esperado]
$casos = [
    ['CA-01', 'registrar sala A', fn () => $antes->registrarSala('A'), fn () => $http->de($servicio->registrarSala('A'), 201), 201],
    ['CA-02', 'sala A repetida', fn () => $antes->registrarSala('A'), fn () => $http->de($servicio->registrarSala('A'), 201), 409],
    ['CA-02', 'sala sin código', fn () => $antes->registrarSala(' '), fn () => $http->de($servicio->registrarSala(' '), 201), 422],
    ['CA-03', 'cama en sala inexistente', fn () => $antes->registrarCama('Z', '1'), fn () => $http->de($servicio->registrarCama('Z', '1'), 201), 422],
    ['CA-04', 'cama A/101 disponible', fn () => $antes->registrarCama('A', '101'), fn () => $http->de($servicio->registrarCama('A', '101'), 201), 201],
    ['CA-04', 'cama A/101 repetida', fn () => $antes->registrarCama('A', '101'), fn () => $http->de($servicio->registrarCama('A', '101'), 201), 409],
    ['CA-05', 'A/101 a mantenimiento', fn () => $antes->cambiarEstado('A', '101', 'maintenance', 'limpieza'), fn () => $http->de($servicio->cambiarEstado('A', '101', 'maintenance', 'limpieza'), 200), 200],
    ['CA-06', 'mantenimiento a ocupada (manual)', fn () => $antes->cambiarEstado('A', '101', 'occupied', 'x'), fn () => $http->de($servicio->cambiarEstado('A', '101', 'occupied', 'x'), 200), 422],
    ['CA-07', 'cambio sin motivo', fn () => $antes->cambiarEstado('A', '101', 'available', ''), fn () => $http->de($servicio->cambiarEstado('A', '101', 'available', ''), 200), 422],
    ['CA-07', 'cama inexistente', fn () => $antes->cambiarEstado('A', '999', 'available', 'x'), fn () => $http->de($servicio->cambiarEstado('A', '999', 'available', 'x'), 200), 404],
];
$fallos = 0;
foreach ($casos as [$ca, $nombre, $a, $d, $esperado]) {
    [$ra, $rd] = [$a(), $d()];
    $ok = $ra === $rd && $rd['status'] === $esperado;
    $fallos += $ok ? 0 : 1;
    printf("%s %-5s %s antes=%d despues=%d\n", $ok ? 'OK  ' : 'FALLA', $ca, mb_str_pad($nombre, 34), $ra['status'], $rd['status']);
}
// CA-08: admisión ocupa la cama 102; nadie puede cambiarla a mano (E5).
$antes->registrarCama('A', '102'); $servicio->registrarCama('A', '102');
$antes->ocuparPorAdmision('A', '102'); $repo->guardarCama('A', '102', 'occupied');
[$ra, $rd] = [$antes->cambiarEstado('A', '102', 'maintenance', 'x'), $http->de($servicio->cambiarEstado('A', '102', 'maintenance', 'x'), 200)];
$ok = $ra === $rd && $rd['error'] === 'E5';
$fallos += $ok ? 0 : 1;
printf("%s CA-08 %-34s antes=%d despues=%d\n", $ok ? 'OK  ' : 'FALLA', 'cama ocupada a mantenimiento', $ra['status'], $rd['status']);

$ok = $antes->bitacora === $auditoria->eventos;
$fallos += $ok ? 0 : 1;
printf("%s auditoría: %d eventos iguales en antes y después\n", $ok ? 'OK  ' : 'FALLA', count($auditoria->eventos));

$politica = new PoliticaEstadosCama();
foreach ([['available', 'maintenance', null], ['inactive', 'maintenance', 'E6'], ['occupied', 'available', 'E5']] as [$de, $a, $esperado]) {
    $ok = $politica->rechazo($de, $a) === $esperado;
    $fallos += $ok ? 0 : 1;
    printf("%s política aislada %s -> %s = %s\n", $ok ? 'OK  ' : 'FALLA', $de, $a, $esperado ?? 'permitido');
}
echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
