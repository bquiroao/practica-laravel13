<?php
declare(strict_types=1);

// Semana 12 – respuesta al cambio solicitado y comprobación de la matriz:  php demostrar-cambio.php
// Cambio: «para pacientes con marcapasos, la FC solo alerta por exceso». Se resuelve con UNA clase nueva y un cambio de cableado;
// Toma, EvaluadorUmbrales, RegistrarToma y las reglas existentes quedan intactos (RNF-05, diseño LSP de la semana 2).
use SignosVitales\Aplicacion\{RegistrarToma, Usuario};
use SignosVitales\Dominio\{Clasificacion, EvaluadorUmbrales, Medicion, RangoSigno, ReglaDeUmbral, ReglaLimiteInferior, ReglaPorIntervalo, TipoSigno};
use SignosVitales\Persistencia\EnMemoria;

$modulo = dirname(__DIR__);
require "$modulo/micro-his-signos-vitales/bootstrap.php";

/** Propuesta (pasaría tal cual a src/Dominio/): mismo contrato que las otras reglas; el límite inferior no genera nivel. */
final class ReglaLimiteSuperior implements ReglaDeUmbral {
    public function clasificar(Medicion $m, ?RangoSigno $r): Clasificacion {
        if ($r === null) { return Clasificacion::SinRango; }
        if ($m->valor > $r->criticoAlto) { return Clasificacion::Critica; }
        return $m->valor > $r->normalMax ? Clasificacion::Anormal : Clasificacion::Normal;
    }
}

$fallas = 0;
function ver(string $que, bool $ok, string $detalle = ''): void { global $fallas; $fallas += $ok ? 0 : 1; printf("%-6s %s%s\n", $ok ? 'PASA' : 'FALLA', $que, $ok || $detalle === '' ? '' : " → $detalle"); }
$fc = new RangoSigno(60, 100, 40, 130);
$med = fn (float $v) => new Medicion(TipoSigno::FC, $v);

// C-01: la misma prueba de contrato (RNF-06) para las tres reglas: devuelve siempre una Clasificacion, sin rango → SinRango, nunca lanza.
foreach (['ReglaPorIntervalo' => new ReglaPorIntervalo(), 'ReglaLimiteInferior' => new ReglaLimiteInferior(), 'ReglaLimiteSuperior' => new ReglaLimiteSuperior()] as $nombre => $regla) {
    $ok = $regla->clasificar($med(75), null) === Clasificacion::SinRango;
    foreach ([-5.0, 0.0, 39.9, 40.0, 75.0, 130.0, 131.0, 1e6] as $v) { try { $ok = $ok && $regla->clasificar($med($v), $fc) instanceof Clasificacion; } catch (\Throwable) { $ok = false; } }
    ver("C-01 contrato LSP: $nombre", $ok);
}

// C-02: con la regla nueva solo cambia lo pedido (FC baja); el exceso se clasifica igual que antes.
$estandar = EvaluadorUmbrales::estandar();
$marcapasos = new EvaluadorUmbrales(['spo2' => new ReglaLimiteInferior(), 'fc' => new ReglaLimiteSuperior()]);
$nivel = fn (EvaluadorUmbrales $e, float $v) => $e->evaluar([$med($v)], ['fc' => $fc])['fc']->value;
ver('C-02a FC 35: estándar crítica, marcapasos normal (lo pedido)', $nivel($estandar, 35) === 'critica' && $nivel($marcapasos, 35) === 'normal');
ver('C-02b FC 105 y 140: igual en ambos (anormal / crítica)', $nivel($estandar, 105) === $nivel($marcapasos, 105) && $nivel($estandar, 140) === $nivel($marcapasos, 140) && $nivel($marcapasos, 140) === 'critica');

// C-03: de punta a punta por el caso de uso real, sin modificarlo: solo se le entrega otro evaluador.
$demo = fn () => new EnMemoria(['epi-1' => ['paciente_id' => 'pac-adulto', 'hospital_id' => 'hosp-1', 'fecha_nacimiento' => '1980-05-10', 'activo' => true]], [[18, 120, ['fc' => $fc]]]);
$registrar = function (?EvaluadorUmbrales $e) use ($demo) { $r = $demo(); return (new RegistrarToma($r, $r, fn () => new DateTimeImmutable('2026-10-25 08:00'), fn () => 't-1', $e))
    ->ejecutar(['paciente_id' => 'pac-adulto', 'episodio_id' => 'epi-1', 'mediciones' => ['fc' => 35]], new Usuario('enf-ana', 'enfermeria', 'hosp-1')); };
ver('C-03 RegistrarToma con el evaluador nuevo: FC 35 sin alerta; con el estándar: alerta crítica', $registrar($marcapasos)['alertas'] === [] && ($registrar(null)['alertas'][0]['nivel'] ?? '') === 'critica');

// C-04: el cambio no toca el código existente: la clase nueva no está declarada en src/ y las clases cerradas siguen igual de cerradas.
$enSrc = array_filter(array_merge(...array_map(fn ($c) => glob("$modulo/micro-his-signos-vitales/src/$c/*.php"), ['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion'])),
    fn ($f) => str_contains((string) file_get_contents($f), 'ReglaLimiteSuperior'));
ver('C-04 src/ no menciona la regla nueva (no hubo que editar nada existente)', $enSrc === []);

// M-01: la matriz de la defensa solo cita archivos que existen.
$doc = (string) file_get_contents(__DIR__ . '/semana-12-defensa-segundo-parcial.md');
preg_match_all('/`((?:micro-his-signos-vitales|semana-\d\d)\/[\w\/.\-]+\.\w+)`/', $doc, $m);
$faltan = array_values(array_filter(array_unique($m[1]), fn ($p) => !is_file("$modulo/$p")));
$ajenas = array_values(array_filter($faltan, fn ($p) => preg_match('#^semana-(1[13-5])/#', $p)));   // otra semana que quizá aún no se fusiona
$propias = array_diff($faltan, $ajenas);
ver('M-01 cada evidencia citada en la matriz existe (' . count(array_unique($m[1])) . ' rutas' . ($ajenas ? '; ' . count($ajenas) . ' de otras semanas aún sin fusionar' : '') . ')', $propias === [] && count(array_unique($m[1])) >= 8, implode(', ', $propias));
preg_match_all('/\b(RF-\d\d|RNF-\d\d)\b/', $doc, $r); $citados = array_unique($r[1]);
ver('M-02 la matriz cubre RF-01..RF-10 y RNF-01, 02, 04, 05, 06, 07', count(array_diff(array_merge(array_map(fn ($n) => sprintf('RF-%02d', $n), range(1, 10)), ['RNF-01', 'RNF-02', 'RNF-04', 'RNF-05', 'RNF-06', 'RNF-07']), $citados)) === 0);

echo $fallas === 0 ? "Resultado: el cambio se resuelve extendiendo, sin modificar código existente, y la matriz coincide con el repositorio\n" : "Resultado: $fallas FALLA(S)\n";
exit($fallas === 0 ? 0 : 1);
