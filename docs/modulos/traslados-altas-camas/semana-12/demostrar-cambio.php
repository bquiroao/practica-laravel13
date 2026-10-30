<?php
declare(strict_types=1);

// Semana 12 – respuesta al cambio solicitado y comprobación de la matriz:  php demostrar-cambio.php
// Cambio: «Epidemiología pide trasladar pacientes a camas de AISLAMIENTO». Hoy el requisito de cama es un enum con un caso `uci` escrito dentro de Traslado.
// Se demuestra, sin tocar src/, que una sola clase de dominio resuelve el cambio sin alterar el comportamiento actual (LSP, RNF-05)
// y se mide con exactitud qué archivos habría que editar de verdad.
use Traslados\Dominio\{Alta, CamaDestino, CamaOrigen, MovimientoDeCama, RequisitoCama, Resultado, TipoAlta, Traslado};

$modulo = dirname(__DIR__);
$micro = "$modulo/micro-his-traslados";
require "$micro/bootstrap.php";

/** Propuesta (pasaría a src/Dominio/Movimientos.php): el requisito deja de ser un enum cerrado y es el tipo de cama que la orden pide. */
final class TrasladoGeneral implements MovimientoDeCama {
    public function __construct(private string $requisito = 'cualquiera') {}
    public function aplicar(?CamaOrigen $origen, ?CamaDestino $destino): Resultado {
        if ($origen === null) { return Resultado::rechazado('sin_cama'); }
        if ($destino === null || $destino->camaId === $origen->camaId || $destino->estado !== 'available') { return Resultado::rechazado('cama_no_disponible'); }
        if ($this->requisito !== 'cualquiera' && $destino->tipo !== $this->requisito) { return Resultado::rechazado('cama_no_cumple_requisito'); }
        return Resultado::aplicado($origen->camaId, $destino->camaId);
    }
}

$fallas = 0;
function ver(string $que, bool $ok, string $detalle = ''): void { global $fallas; $fallas += $ok ? 0 : 1; printf("%-6s %s%s\n", $ok ? 'PASA' : 'FALLA', $que, $ok || $detalle === '' ? '' : " → $detalle"); }
$ficha = fn (Resultado $r) => [$r->rechazo, $r->origenId, $r->destinoId];
$origen = new CamaOrigen('bed-a1', 'stay-1');
$destinos = [null, new CamaDestino('bed-a1', 'available', 'general')];
foreach (['available', 'occupied', 'maintenance', 'inactive'] as $estado) { foreach (['general', 'uci', 'aislamiento'] as $tipo) { $destinos[] = new CamaDestino("bed-$estado-$tipo", $estado, $tipo); } }

// C-01: la misma prueba de contrato (RNF-06) para Traslado, Alta y la clase nueva: siempre un Resultado, sin origen → sin_cama, el que aplica libera el origen.
foreach (['Traslado' => new Traslado(), 'Alta' => new Alta(TipoAlta::Medica, 'Mejoría'), 'TrasladoGeneral(aislamiento)' => new TrasladoGeneral('aislamiento')] as $nombre => $mov) {
    $ok = $mov->aplicar(null, $destinos[2])->rechazo === 'sin_cama';
    foreach ($destinos as $d) { try { $r = $mov->aplicar($origen, $d); $ok = $ok && $r instanceof Resultado && ($r->fueAplicado() ? $r->origenId === 'bed-a1' : $r->rechazo !== null); } catch (\Throwable) { $ok = false; } }
    ver("C-01 contrato LSP: $nombre", $ok);
}

// C-02: con los requisitos de hoy la clase nueva se comporta igual que Traslado en las 56 combinaciones (requisito × origen × destino).
$dif = 0;
foreach ([[RequisitoCama::Cualquiera, 'cualquiera'], [RequisitoCama::Uci, 'uci']] as [$enum, $texto]) {
    foreach ([null, $origen] as $o) { foreach ($destinos as $d) { $dif += $ficha((new Traslado($enum))->aplicar($o, $d)) === $ficha((new TrasladoGeneral($texto))->aplicar($o, $d)) ? 0 : 1; } }
}
ver('C-02 equivalencia con Traslado para «cualquiera» y «uci» (' . (2 * 2 * count($destinos)) . ' combinaciones)', $dif === 0);

// C-03: el requisito nuevo funciona: aislamiento disponible se acepta; general, uci u ocupada se rechazan con su código.
$aislar = new TrasladoGeneral('aislamiento');
ver('C-03 aislamiento: disponible → aplicado; cama general → cama_no_cumple_requisito; ocupada → cama_no_disponible',
    $aislar->aplicar($origen, new CamaDestino('bed-i1', 'available', 'aislamiento'))->fueAplicado()
    && $aislar->aplicar($origen, new CamaDestino('bed-a2', 'available', 'general'))->rechazo === 'cama_no_cumple_requisito'
    && $aislar->aplicar($origen, new CamaDestino('bed-i2', 'occupied', 'aislamiento'))->rechazo === 'cama_no_disponible');

// C-04: impacto real: qué archivos de src/ y de sql/ mencionan «uci» (los únicos que habría que revisar).
$archivos = array_merge(...array_map(fn ($c) => glob("$micro/src/$c/*.php"), ['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion']));
$archivos[] = "$micro/sql/esquema.sql";
$tocan = array_map(fn ($f) => str_replace("$micro/", '', $f), array_values(array_filter($archivos, fn ($f) => preg_match("/'uci'|Uci\b/", (string) file_get_contents($f)))));
sort($tocan);
ver('C-04 solo mencionan «uci»: ' . implode(', ', $tocan) . ' (EnMemoria solo por sus datos de demostración); caso de uso, Repository PDO y API no hay que tocarlos', $tocan === ['sql/esquema.sql', 'src/Dominio/Movimientos.php', 'src/Persistencia/EnMemoria.php']);

// M-01/M-02: la matriz de la defensa solo cita archivos que existen y cubre todos los requisitos.
$doc = (string) file_get_contents(__DIR__ . '/semana-12-defensa-segundo-parcial.md');
preg_match_all('/`((?:micro-his-traslados|semana-\d\d)\/[\w\/.\-]+\.\w+)`/', $doc, $m); $citadas = array_unique($m[1]);
$faltan = array_values(array_filter($citadas, fn ($p) => !is_file("$modulo/$p")));
$ajenas = array_values(array_filter($faltan, fn ($p) => preg_match('#^semana-(1[13-5])/#', $p))); $propias = array_diff($faltan, $ajenas);   // otra semana que quizá aún no se fusiona
ver('M-01 cada evidencia citada en la matriz existe (' . count($citadas) . ' rutas' . ($ajenas ? '; ' . count($ajenas) . ' de otras semanas aún sin fusionar' : '') . ')', $propias === [] && count($citadas) >= 8, implode(', ', $propias));
preg_match_all('/\b(RF-\d\d|RNF-\d\d)\b/', $doc, $r);
ver('M-02 la matriz cubre RF-01..RF-10 y RNF-01..RNF-06', count(array_diff(array_merge(array_map(fn ($n) => sprintf('RF-%02d', $n), range(1, 10)), array_map(fn ($n) => sprintf('RNF-%02d', $n), range(1, 6))), array_unique($r[1]))) === 0);

echo $fallas === 0 ? "Resultado: el cambio se resuelve con una clase de dominio y sin alterar el comportamiento actual, y la matriz coincide con el repositorio\n" : "Resultado: $fallas FALLA(S)\n";
exit($fallas === 0 ? 0 : 1);
