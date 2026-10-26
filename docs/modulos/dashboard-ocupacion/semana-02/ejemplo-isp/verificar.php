<?php
declare(strict_types=1);

// Evidencia ISP: (1) consolidación y consulta cumplen CA-01..CA-08 con datos ficticios;
// (2) por reflexión, cada cliente depende solo de lo que usa y ninguno puede escribir camas.
require __DIR__ . '/antes.php';
require __DIR__ . '/despues.php';

$fallos = 0;
function verificar(bool $ok, string $texto): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . $texto . "\n"; }

// Sala A: A1 ocupada con estancia, A2 disponible, A3 mantenimiento, A4 ocupada SIN estancia, A5 disponible CON estancia.
// Sala B: solo mantenimiento e inactiva. La sala C está inactiva, así que la fuente no la devuelve (R-03).
$fuente = new FuenteEnMemoria(['A', 'B'],
    ['A' => ['A1' => 'occupied', 'A2' => 'available', 'A3' => 'maintenance', 'A4' => 'occupied', 'A5' => 'available'], 'B' => ['B1' => 'maintenance', 'B2' => 'inactive']],
    ['A/A1', 'A/A5']);
$cortes = new AlmacenCortesEnMemoria();
$consolidador = new ConsolidadorOcupacion($fuente, $cortes);
$consulta = new ConsultaOcupacion($cortes);

verificar($consulta->consultar(0) === ['status' => 200, 'vacio' => true], 'CA-04 sin corte todavía = estado vacío');
verificar($consolidador->consolidar(1000), 'CA-01 consolidar a la hora 1000');
$r = $consulta->consultar(1000 + 5 * 60);
verificar($r['salas']['A']['ocupacion'] === 50.0, 'CA-01 sala A: 2 ocupadas ÷ (2 disponibles + 2 ocupadas) = 50.0 %; mantenimiento no cuenta');
verificar($r['salas']['B']['ocupacion'] === 'no aplica', 'CA-02 sala B sin camas operativas = «no aplica»');
verificar(!isset($r['salas']['C']), 'CA-02 la sala inactiva C no aparece');
verificar($r['salas']['A']['inconsistentes'] === ['A4', 'A5'], 'CA-03 inconsistencias: A4 (ocupada sin estancia) y A5 (estancia sin ocupar)');
verificar($r['aviso_viejo'] === false, 'CA-05 corte de hace 5 min: sin aviso');
verificar($consulta->consultar(1000 + 20 * 60)['aviso_viejo'] === true, 'CA-05 corte de hace 20 min: aviso de datos viejos');
verificar(array_keys($consulta->consultar(1300, 'available')['salas']) === ['A'], 'CA-06 filtro «disponible»: solo la sala A');
$fuente->falla = true;
verificar(!$consolidador->consolidar(2000) && $consulta->consultar(2000)['hora'] === 1000, 'CA-07 falla la lectura: se conserva el corte de la hora 1000');
verificar(!str_contains(json_encode($cortes->ultimoCorte()), 'paciente'), 'CA-08 el corte solo tiene cifras, sin datos de pacientes');

$deps = fn (string $clase) => array_map(fn ($p) => $p->getType()->getName(), (new ReflectionMethod($clase, '__construct'))->getParameters());
$metodos = fn (string $interfaz) => array_map(fn ($m) => $m->getName(), (new ReflectionClass($interfaz))->getMethods());
foreach (['ConsolidadorOcupacion' => [3, 1], 'ConsultaOcupacion' => [1], 'ConsolidadorAntes' => [6], 'ConsultaOcupacionAntes' => [6]] as $clase => $esperado) {
    verificar(array_map(fn ($i) => count($metodos($i)), $deps($clase)) === $esperado, "$clase depende de " . implode(' + ', $esperado) . ' método(s): ' . implode(', ', $deps($clase)));
}
$todos = array_merge(...array_map($metodos, ['FuenteCamas', 'EscritorCortes', 'LectorCortes']));
verificar(preg_grep('/cambiar|asignar|liberar/i', $todos) === [], 'Ninguna interfaz del tablero permite cambiar camas (R-09)');
verificar(in_array('cambiarEstadoCama', $metodos('AlmacenOcupacionAntes'), true), 'Antes: el tablero dependía de cambiarEstadoCama()');

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
