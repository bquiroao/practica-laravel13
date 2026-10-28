<?php
declare(strict_types=1);

// Evidencia de la semana 2. Uso: php verificar.php   (sale con 1 si algo no da lo esperado)
require __DIR__ . '/antes.php';
require __DIR__ . '/despues.php';

use SignosVitales\Antes as A;
use SignosVitales\Despues as D;

$fallas = 0;
function ver(string $id, bool $ok, string $detalle): void {
    $GLOBALS['fallas'] += $ok ? 0 : 1;
    printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $detalle);
}

// Prueba de contrato: los mismos casos para cualquier implementación.
// C1 no lanza · C2 devuelve un valor del contrato · C3 sin rango → sin_rango · C4 debajo del crítico → critica
function contrato(callable $clasificar): array {
    $v = ['C1' => 0, 'C2' => 0, 'C3' => 0, 'C4' => 0];
    foreach (D\rangosAdulto() as $signo => $r) {
        [$min, $max] = D\ValidadorLimitesFisicos::LIMITES[$signo];
        $valores = array_filter([$min, $r->criticoBajo - 1, $r->criticoBajo, $r->normalMin, $r->normalMax, $r->criticoAlto, $r->criticoAlto + 1, $max], fn ($x) => $x >= $min && $x <= $max);
        foreach ($valores as $x) {
            foreach ([$r, null] as $rango) {
                try { $c = $clasificar($x, $rango); } catch (\Throwable) { $v['C1']++; continue; }
                $v['C2'] += in_array($c, ['normal', 'anormal', 'critica', 'sin_rango'], true) ? 0 : 1;
                $v['C3'] += ($rango === null && $c !== 'sin_rango') ? 1 : 0;
                $v['C4'] += ($rango !== null && $x < $rango->criticoBajo && $c !== 'critica') ? 1 : 0;
            }
        }
    }
    return $v;
}
$comoArreglo = fn (?D\RangoSigno $r) => $r ? [$r->normalMin, $r->normalMax, $r->criticoBajo, $r->criticoAlto] : [];
$resumen = fn (array $v) => implode(' ', array_map(fn ($k) => "$k=" . $v[$k], array_keys($v)));

echo "== ANTES: ¿cada subclase puede reemplazar a EvaluadorSigno?\n";
$rompen = 0;
foreach ([A\EvaluadorSigno::class, A\EvaluadorPresion::class, A\EvaluadorSaturacion::class, A\EvaluadorTemperatura::class] as $clase) {
    $v = contrato(fn (float $x, ?D\RangoSigno $r) => (new $clase($comoArreglo($r)))->evaluar($x));
    $rompen += array_sum($v) > 0 ? 1 : 0;
    printf("        %-22s %s %s\n", substr(strrchr($clase, '\\'), 1), array_sum($v) ? 'ROMPE LSP' : 'cumple   ', $resumen($v));
}
ver('LSP-A', $rompen === 3, "3 de 3 subclases rompen el contrato de la base (V1, V2, V3)");

echo "== DESPUÉS: ¿cada regla cumple ReglaDeUmbral?\n";
$reglas = ['ReglaPorIntervalo' => new D\ReglaPorIntervalo(), 'ReglaLimiteInferior' => new D\ReglaLimiteInferior()];
foreach ($reglas as $nombre => $regla) {
    $v = contrato(fn (float $x, ?D\RangoSigno $r) => $regla->clasificar(new D\Medicion(D\TipoSigno::FC, $x), $r)->value);
    ver('LSP-D', array_sum($v) === 0, "$nombre: " . $resumen($v));
}

echo "== Efecto en el cliente (temperatura de 43 °C, valor posible y crítico)\n";
$antes = A\evaluarToma(['temp' => new A\EvaluadorTemperatura([36.0, 37.5, 35.0, 39.9])], ['temp' => 43.0]);
$despues = D\evaluadorEstandar()->evaluar([new D\Medicion(D\TipoSigno::TEMP, 43.0)], D\rangosAdulto());
ver('CLI-01', $antes['temp'] === 'revisar_termometro' && $despues['temp'] === D\Clasificacion::Critica, "antes «{$antes['temp']}» (sin alerta) · después «{$despues['temp']->value}»");

echo "== Criterios de aceptación\n";
$m = fn (string $s, float $x) => new D\Medicion(D\TipoSigno::from($s), $x);
$ev = D\evaluadorEstandar();
$val = new D\ValidadorLimitesFisicos();
ver('CA-04a', $val->invalidas([$m('fc', 400), $m('temp', 36.8)]) === ['fc'], 'FC 400 lpm es imposible: se rechaza y se marca «fc»');
ver('CA-04b', $val->invalidas([$m('fc', 20), $m('spo2', 100)]) === [], 'FC 20 y SpO2 100 están en el límite físico: se aceptan');
$c = $ev->evaluar([$m('fc', 75), $m('fc', 110)], D\rangosAdulto());
ver('CA-05a', $ev->evaluar([$m('fc', 75)], D\rangosAdulto())['fc'] === D\Clasificacion::Normal && $c['fc'] === D\Clasificacion::Anormal, 'FC 75 → normal; FC 110 → anormal');
$c = $ev->evaluar([$m('fc', 131), $m('spo2', 89), $m('pas', 180), $m('temp', 40.0)], D\rangosAdulto());
ver('CA-05b', array_values(array_unique(array_map(fn ($x) => $x->value, $c))) === ['critica'], 'FC 131, SpO2 89, PAS 180 y temperatura 40.0 → crítica');
ver('CA-05c', $ev->evaluar([$m('fc', 150)], [])['fc'] === D\Clasificacion::SinRango, 'Sin rango para la edad (pediatría) → sin_rango, sin inventar umbral');
$alertas = (new D\GeneradorAlertas())->alertas($ev->evaluar([$m('fc', 110), $m('spo2', 88), $m('fr', 16)], D\rangosAdulto()));
ver('CA-06', $alertas === [['signo' => 'fc', 'nivel' => 'anormal'], ['signo' => 'spo2', 'nivel' => 'critica']], '1 anormal + 1 crítica + 1 normal → 2 alertas');

echo "== RNF-05: una regla nueva se conecta sin tocar EvaluadorUmbrales\n";
$conMargen = new class implements D\ReglaDeUmbral {
    public function clasificar(D\Medicion $m, ?D\RangoSigno $r): D\Clasificacion {   // ejemplo: la unidad pide avisar 5 lpm antes del límite normal alto
        return $r === null ? D\Clasificacion::SinRango : (new D\ReglaPorIntervalo())->clasificar($m, new D\RangoSigno($r->normalMin, $r->normalMax - 5, $r->criticoBajo, $r->criticoAlto));
    }
};
$v = contrato(fn (float $x, ?D\RangoSigno $r) => $conMargen->clasificar(new D\Medicion(D\TipoSigno::FC, $x), $r)->value);
$c = (new D\EvaluadorUmbrales(['fc' => $conMargen]))->evaluar([$m('fc', 97)], D\rangosAdulto());
ver('RNF-05', array_sum($v) === 0 && $c['fc'] === D\Clasificacion::Anormal, 'regla nueva cumple el contrato y FC 97 → anormal: ' . $resumen($v));

echo $fallas === 0 ? "\nResultado: todo lo esperado se cumple.\n" : "\nResultado: $fallas verificaciones fallaron.\n";
exit($fallas === 0 ? 0 : 1);
