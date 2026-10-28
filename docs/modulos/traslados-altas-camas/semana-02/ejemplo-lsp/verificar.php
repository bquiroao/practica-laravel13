<?php
declare(strict_types=1);

// Evidencia de la semana 2. Uso: php verificar.php   (sale con 1 si algo no da lo esperado)
require __DIR__ . '/antes.php';
require __DIR__ . '/despues.php';

use Traslados\Antes as A;
use Traslados\Despues as D;

$fallas = 0;
function ver(string $id, bool $ok, string $detalle): void {
    $GLOBALS['fallas'] += $ok ? 0 : 1;
    printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $detalle);
}

// Prueba de contrato: cada caso es [aplicar, paciente, destino o null, ¿debe aplicarse?].
// C1 no lanza · C2 el estado sigue consistente · C3 si se aplica, el paciente deja su cama y esta queda en limpieza · C4 si se rechaza, nada cambia
function contrato(array $casos): array {
    $v = ['C1' => 0, 'C2' => 0, 'C3' => 0, 'C4' => 0];
    foreach ($casos as [$aplicar, $paciente, $destino, $exito]) {
        $antes = D\camasEjemplo();
        $origen = D\Camas::de($antes, $paciente);
        try { [$camas, $rechazo] = $aplicar($antes); } catch (\Throwable) { $v['C1']++; continue; }
        $v['C2'] += D\consistente($camas) ? 0 : 1;
        if ($exito) {
            $libero = $camas[$origen]['estado'] === 'maintenance' && $camas[$origen]['paciente'] === null;
            $llego = $destino === null || $camas[$destino]['paciente'] === $paciente;
            $v['C3'] += ($rechazo === null && $libero && $llego) ? 0 : 1;
        } else {
            $v['C4'] += ($camas === $antes) ? 0 : 1;
        }
    }
    return $v;
}
$resumen = fn (array $v) => implode(' ', array_map(fn ($k) => "$k=" . $v[$k], array_keys($v)));
$viejo = fn (A\Movimiento $m) => fn (array $c) => [$m->aplicar($c), null];
$nuevo = fn (D\MovimientoDeCama $m) => fn (array $c) => [($r = $m->aplicar($c))->camas, $r->rechazo];

echo "== ANTES: ¿cada subclase cumple lo que el cliente espera de Movimiento?\n";
$antes = [
    'Traslado' => [[$viejo(new A\Traslado('p1', 'A2')), 'p1', 'A2', true], [$viejo(new A\Traslado('p1', 'A3')), 'p1', 'A3', false]],
    'Alta' => [[$viejo(new A\Alta('p1')), 'p1', null, true], [$viejo(new A\Alta('p9')), 'p9', null, false]],
    'AltaVoluntaria' => [[$viejo(new A\AltaVoluntaria('p1')), 'p1', null, true]],
    'TrasladoUci' => [[$viejo(new A\TrasladoUci('p1', 'U1')), 'p1', 'U1', true], [$viejo(new A\TrasladoUci('p1', 'A2')), 'p1', 'A2', false]],
    'TrasladoTemporal' => [[$viejo(new A\TrasladoTemporal('p1', 'A2')), 'p1', 'A2', true], [$viejo(new A\TrasladoTemporal('p1', 'A3')), 'p1', 'A3', false]],
];
$rompen = 0;
foreach ($antes as $clase => $casos) {
    $v = contrato($casos);
    $rompen += array_sum($v) > 0 ? 1 : 0;
    printf("        %-17s %s %s\n", $clase, array_sum($v) ? 'ROMPE LSP' : 'cumple   ', $resumen($v));
}
ver('LSP-A', $rompen === 3, '3 subclases rompen el contrato (V1 AltaVoluntaria, V2 TrasladoUci, V3 TrasladoTemporal)');

echo "== DESPUÉS: ¿cada implementación cumple MovimientoDeCama?\n";
$uci = D\RequisitoCama::Uci;
$despues = [
    'Traslado' => [[$nuevo(new D\Traslado('p1', 'A2')), 'p1', 'A2', true], [$nuevo(new D\Traslado('p1', 'A3')), 'p1', 'A3', false],
                   [$nuevo(new D\Traslado('p9', 'A2')), 'p9', 'A2', false], [$nuevo(new D\Traslado('p1', 'M1')), 'p1', 'M1', false],
                   [$nuevo(new D\Traslado('p1', 'U1', $uci)), 'p1', 'U1', true], [$nuevo(new D\Traslado('p1', 'A2', $uci)), 'p1', 'A2', false]],
    'Alta' => [[$nuevo(new D\Alta('p1', D\TipoAlta::Medica, 'Mejoría')), 'p1', null, true], [$nuevo(new D\Alta('p1', D\TipoAlta::Voluntaria, 'Solicitud del paciente')), 'p1', null, true],
               [$nuevo(new D\Alta('p2', D\TipoAlta::Defuncion, 'Defunción')), 'p2', null, true], [$nuevo(new D\Alta('p1', D\TipoAlta::Referencia, '  ')), 'p1', null, false],
               [$nuevo(new D\Alta('p9', D\TipoAlta::Medica, 'Mejoría')), 'p9', null, false]],
];
foreach ($despues as $clase => $casos) { $v = contrato($casos); ver('LSP-D', array_sum($v) === 0, "$clase: " . $resumen($v)); }

echo "== Efecto en el cliente (paciente que va a quirófano y vuelve)\n";
$cliente = A\registrarMovimiento(new A\TrasladoTemporal('p1', 'A2'), D\camasEjemplo());
$salida = (new D\SalidaTemporal('p1', 'quirófano'))->registrar(D\camasEjemplo());
ver('CLI-01', !D\consistente($cliente) && $salida['cama_reservada'] === 'A1', 'antes: p1 queda en A1 y A2 a la vez · después: SalidaTemporal no toca camas y reserva A1');

echo "== Criterios de aceptación\n";
$caso = new D\RegistrarMovimiento();
$r = fn (D\MovimientoDeCama $m) => $caso->ejecutar($m, D\camasEjemplo());
$t = $r(new D\Traslado('p1', 'A2'));
ver('CA-03', ($x = $r(new D\Traslado('p1', 'A3')))->rechazo === 'cama_no_disponible' && $x->camas === D\camasEjemplo(), 'Traslado a cama ocupada → cama_no_disponible y nada cambia');
ver('CA-04', $t->camas['A2']['paciente'] === 'p1' && $t->camas['A1']['estado'] === 'maintenance', 'Traslado válido: p1 en A2 y A1 queda en limpieza en el mismo resultado');
ver('CA-05', $r(new D\Alta('p1', D\TipoAlta::Voluntaria, 'Solicitud'))->camas['A1']['estado'] === 'maintenance', 'Alta voluntaria también libera la cama');
ver('CA-06', $r(new D\Traslado('p1', 'A2', $uci))->rechazo === 'cama_no_cumple_requisito', 'Traslado a UCI con cama general → cama_no_cumple_requisito (sin excepción)');
ver('CA-07', $r(new D\Alta('p1', D\TipoAlta::Medica, ''))->rechazo === 'alta_invalida', 'Alta sin motivo → alta_invalida');
ver('CA-08', $r(new D\Traslado('p9', 'A2'))->rechazo === 'sin_cama', 'Paciente sin cama → sin_cama');

echo "== RNF-05: un movimiento nuevo se conecta sin tocar RegistrarMovimiento\n";
$conEpicrisis = fn (bool $lista) => new class ($lista) implements D\MovimientoDeCama {
    public function __construct(private bool $epicrisisLista) {}
    public function aplicar(array $camas): D\Resultado {   // ejemplo: el hospital exige la epicrisis antes del alta
        return $this->epicrisisLista ? (new D\Alta('p1', D\TipoAlta::Medica, 'Mejoría'))->aplicar($camas) : new D\Resultado($camas, 'alta_invalida');
    }
};
$v = contrato([[$nuevo($conEpicrisis(true)), 'p1', null, true], [$nuevo($conEpicrisis(false)), 'p1', null, false]]);
ver('RNF-05', array_sum($v) === 0, 'AltaConEpicrisis (clase anónima) cumple el contrato: ' . $resumen($v));

echo $fallas === 0 ? "\nResultado: todo lo esperado se cumple.\n" : "\nResultado: $fallas verificaciones fallaron.\n";
exit($fallas === 0 ? 0 : 1);
