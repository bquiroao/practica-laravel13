<?php
declare(strict_types=1);

// Semana 15: pruebas unitarias (U), de caja blanca (W) y de caja negra (B) trazadas a RF/RNF.  php tests/semana-15.php
// Un defecto conocido (acta de la semana 13) se prueba con defecto(): ABIERTO mientras se reproduce; si deja de reproducirse, FALLA y pide pasarlo a prueba normal.
use SignosVitales\Aplicacion\{ConsultarTomas, RegistrarToma, Usuario};
use SignosVitales\Dominio\{Clasificacion, ErrorDominio, EvaluadorUmbrales, Medicion, RangoSigno, ReglaLimiteInferior, ReglaPorIntervalo, TipoSigno, Toma, ValidadorLimitesFisicos};
use SignosVitales\Persistencia\TomasPdo;
use SignosVitales\Presentacion\{Enrutador, Peticion, TomasControlador};

require __DIR__ . '/../bootstrap.php';

$conteo = ['PASA' => 0, 'FALLA' => 0, 'ABIERTO' => 0];
function prueba(string $id, string $trazas, string $que, callable $cuerpo): void {
    try { $cuerpo(); $estado = 'PASA'; $detalle = ''; } catch (\Throwable $e) { $estado = 'FALLA'; $detalle = ' → ' . $e->getMessage(); }
    $GLOBALS['conteo'][$estado]++; printf("%-5s %-8s %-21s %s%s\n", $id, $estado, $trazas, $que, $detalle);
}
function defecto(string $id, string $hallazgo, string $trazas, string $que, callable $cuerpo): void {
    try { $cuerpo(); $estado = 'ABIERTO'; $detalle = " ($hallazgo)"; } catch (\Throwable $e) { $estado = 'FALLA'; $detalle = " → ya no se reproduce ($hallazgo): conviértalo en prueba normal. " . $e->getMessage(); }
    $GLOBALS['conteo'][$estado]++; printf("%-5s %-8s %-21s %s%s\n", $id, $estado, $trazas, $que, $detalle);
}
function igual(mixed $esperado, mixed $real, string $que = 'valor'): void { if ($esperado !== $real) { throw new \RuntimeException("$que: esperado " . json_encode($esperado) . ', obtenido ' . json_encode($real)); } }
function errorDe(callable $f): \Throwable { try { $f(); } catch (\Throwable $e) { return $e; } throw new \RuntimeException('se esperaba un error'); }
function base(): PDO { $pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents(__DIR__ . '/../sql/esquema.sql')); return $pdo; }
function api(PDO $pdo): Enrutador {
    $r = new TomasPdo($pdo); $caso = new RegistrarToma($r, $r, fn () => new DateTimeImmutable('2026-10-25 08:00'), fn () => bin2hex(random_bytes(4)));
    return new Enrutador(new TomasControlador($caso, new ConsultarTomas($r, $r), ['ana' => new Usuario('enf-ana', 'enfermeria', 'hosp-1'), 'luis' => new Usuario('dr-luis', 'medico', 'hosp-1'), 'otra' => new Usuario('enf-otra', 'enfermeria', 'hosp-2')]));
}
function http(Enrutador $e, string $metodo, array $cuerpo = [], string $usuario = 'ana', array $q = []): array { return $e->atender(new Peticion($metodo, '/api/v1/signos-vitales/tomas', $q, $usuario, json_encode($cuerpo))); }
function toma(array $mediciones, string $pac = 'pac-adulto', string $epi = 'epi-1'): array { return ['paciente_id' => $pac, 'episodio_id' => $epi, 'mediciones' => $mediciones]; }
$fc = new RangoSigno(60, 100, 40, 130);
$med = fn (string $t, float $v) => new Medicion(TipoSigno::from($t), $v);

// ── Unitarias ─────────────────────────────────────────────────────────────────
prueba('U-01', 'RF-04', 'Límites físicos: el borde exacto se acepta y un paso más allá se rechaza, nombrando el signo', function () use ($med) {
    (new ValidadorLimitesFisicos())->exigirPosibles([$med('fc', 20), $med('fc', 300), $med('spo2', 100), $med('temp', 25), $med('temp', 45)]);
    foreach ([['fc', 19.9], ['fc', 300.1], ['spo2', 100.1], ['temp', 24.9], ['temp', 45.1], ['pas', 39.9]] as [$t, $v]) {
        $e = errorDe(fn () => (new ValidadorLimitesFisicos())->exigirPosibles([$med('fc', 80), $med($t, $v)])); igual([$t], $e->campos, "campos con $t=$v");
    }
});
prueba('U-02', 'RF-05', 'ReglaPorIntervalo con FC 60–100 y crítico <40 o >130: valores justo en cada frontera', function () use ($fc, $med) {
    $r = new ReglaPorIntervalo();
    foreach ([[39.9, 'critica'], [40, 'anormal'], [59.9, 'anormal'], [60, 'normal'], [100, 'normal'], [100.1, 'anormal'], [130, 'anormal'], [130.1, 'critica']] as [$v, $esperado]) {
        igual($esperado, $r->clasificar($med('fc', $v), $fc)->value, "FC $v");
    }
});
prueba('U-03', 'RF-05, RNF-04', 'ReglaLimiteInferior (SpO2): solo el valor bajo es peligroso; 100 es normal; sin rango → sin_rango', function () use ($med) {
    $r = new ReglaLimiteInferior(); $spo2 = new RangoSigno(95, 100, 90, 100);
    foreach ([[100, 'normal'], [95, 'normal'], [94.9, 'anormal'], [90, 'anormal'], [89.9, 'critica']] as [$v, $esperado]) { igual($esperado, $r->clasificar($med('spo2', $v), $spo2)->value, "SpO2 $v"); }
    igual(Clasificacion::SinRango, $r->clasificar($med('spo2', 80), null));
});
prueba('U-04', 'RF-01, RF-06', 'Toma: vacía o con un signo repetido se rechaza; una alerta por cada medición anormal o crítica', function () use ($fc, $med) {
    $reg = fn (array $m) => Toma::registrar('t', 'p', 'e', 'a', new DateTimeImmutable('2026-10-25'), $m, ['fc' => $fc, 'spo2' => new RangoSigno(95, 100, 90, 100), 'temp' => new RangoSigno(36, 37.5, 35, 39.9)], EvaluadorUmbrales::estandar());
    igual('toma_vacia', errorDe(fn () => $reg([]))->codigo); igual('signo_repetido', errorDe(fn () => $reg([$med('fc', 80), $med('fc', 90)]))->codigo);
    $t = $reg([$med('fc', 135), $med('spo2', 93), $med('temp', 36.8)]);
    igual([['signo' => 'fc', 'nivel' => 'critica', 'valor' => 135.0], ['signo' => 'spo2', 'nivel' => 'anormal', 'valor' => 93.0]], $t->alertas());
});

// ── Caja blanca (ramas del código) ────────────────────────────────────────────
prueba('W-01', 'RF-01, RF-04', 'Medicion::desde recorre sus tres ramas: signo desconocido, valor de texto, valor numérico (entero o decimal)', function () {
    igual('medicion_invalida', errorDe(fn () => Medicion::desde('glucosa', 90))->codigo);
    igual(['fc'], errorDe(fn () => Medicion::desde('fc', '80'))->campos); igual(['fc'], errorDe(fn () => Medicion::desde('fc', true))->campos);
    igual(80.0, Medicion::desde('fc', 80)->valor); igual(36.8, Medicion::desde('temp', 36.8)->valor);
});
prueba('W-02', 'RF-02, RF-03, RNF-02', 'RegistrarToma recorre cada guarda en orden: rol → episodio inexistente → hospital ajeno → sin mediciones', function () {
    $pdo = base(); $e = api($pdo);
    igual([403, 'acceso_denegado'], [http($e, 'POST', toma(['fc' => 80]), 'luis')[0], http($e, 'POST', toma(['fc' => 80]), 'luis')[1]['codigo']]);
    igual(409, http($e, 'POST', toma(['fc' => 80], 'pac-adulto', 'no-existe'))[0]);
    igual('acceso_denegado', http($e, 'POST', toma(['fc' => 80]), 'otra')[1]['codigo']);
    igual('toma_vacia', http($e, 'POST', toma([]))[1]['codigo']);
    igual(0, (int) $pdo->query('SELECT COUNT(*) FROM tomas_signos_vitales')->fetchColumn(), 'ninguna guarda dejó datos');
});
prueba('W-03', 'RF-05, RNF-01', 'Frontera de edad en la consulta de rangos (17, 18, 120 y 121 años) y estado de envío según el nivel', function () {
    $pdo = base(); $pdo->exec("INSERT INTO pacientes VALUES ('p17', '2009-10-26'), ('p18', '2008-10-25'), ('p120', '1906-10-25'), ('p121', '1905-10-25')");
    foreach (['p17', 'p18', 'p120', 'p121'] as $p) { $pdo->exec("INSERT INTO episodios VALUES ('e-$p', '$p', 'hosp-1', 'activo')"); }
    $e = api($pdo); $nivel = fn (string $p) => http($e, 'POST', toma(['fc' => 135], $p, "e-$p"))[1]['clasificaciones']['fc'];
    igual(['sin_rango', 'critica', 'critica', 'sin_rango'], [$nivel('p17'), $nivel('p18'), $nivel('p120'), $nivel('p121')], 'FC 135 según la edad');
    igual([['critica', 'pendiente_envio']], array_map('array_values', $pdo->query('SELECT nivel, estado_envio FROM alertas_signos_vitales WHERE nivel = \'critica\' GROUP BY nivel, estado_envio')->fetchAll(PDO::FETCH_ASSOC)));
});

// ── Caja negra (solo la API HTTP) ─────────────────────────────────────────────
prueba('B-01', 'RF-01, RF-04', 'Valores en el límite físico se aceptan (201) y uno por fuera rechaza TODA la toma nombrando el signo (422)', function () {
    $pdo = base(); $e = api($pdo);
    igual(201, http($e, 'POST', toma(['spo2' => 100, 'temp' => 25, 'fc' => 20]))[0]); igual(201, http($e, 'POST', toma(['temp' => 45]))[0]);
    [$estado, $c] = http($e, 'POST', toma(['fc' => 19.9, 'temp' => 36.8])); igual([422, ['fc']], [$estado, $c['campos']]);
    igual(2, (int) $pdo->query('SELECT COUNT(*) FROM tomas_signos_vitales')->fetchColumn(), 'la toma rechazada no dejó nada');
});
prueba('B-02', 'RF-02, RF-08, RNF-02', 'Sin sesión 401; el médico no registra (403) pero sí consulta (200); otro hospital no ve las tomas (403)', function () {
    $e = api(base()); http($e, 'POST', toma(['fc' => 80]));
    igual(401, http($e, 'POST', toma(['fc' => 80]), 'nadie')[0]); igual(403, http($e, 'POST', toma(['fc' => 80]), 'luis')[0]);
    $q = ['paciente_id' => 'pac-adulto', 'episodio_id' => 'epi-1'];
    [$estado, $c] = http($e, 'GET', [], 'luis', $q); igual([200, 1], [$estado, count($c['tomas'] ?? $c)]); igual(403, http($e, 'GET', [], 'otra', $q)[0]);
});
prueba('B-03', 'RF-03', 'Episodio cerrado → 409; un episodio que no es del paciente → 409; nada se guarda', function () {
    $pdo = base(); $e = api($pdo);
    igual(409, http($e, 'POST', toma(['fc' => 80], 'pac-adulto', 'epi-cerrado'))[0]); igual(409, http($e, 'POST', toma(['fc' => 80], 'pac-nino', 'epi-1'))[0]);
    igual(0, (int) $pdo->query('SELECT COUNT(*) FROM tomas_signos_vitales')->fetchColumn());
});
prueba('B-04', 'RF-10', 'No hay forma de editar ni borrar una toma: PUT, PATCH y DELETE responden 405 y la toma sigue igual', function () {
    $pdo = base(); $e = api($pdo); http($e, 'POST', toma(['fc' => 80]));
    foreach (['PUT', 'PATCH', 'DELETE'] as $m) { igual(405, http($e, $m, toma(['fc' => 10]))[0], $m); }
    igual([80.0], array_map('floatval', $pdo->query('SELECT valor FROM mediciones_signo')->fetchAll(PDO::FETCH_COLUMN)));
});
prueba('B-05', 'RF-06, RNF-04', 'Adulto con SpO2 89 y FC 135: ambas críticas, 2 alertas y la clasificación vuelve en la respuesta', function () {
    [$estado, $c] = http(api(base()), 'POST', toma(['spo2' => 89, 'fc' => 135, 'temp' => 36.8]));
    igual([201, ['spo2' => 'critica', 'fc' => 'critica', 'temp' => 'normal']], [$estado, $c['clasificaciones']]); igual(2, count($c['alertas']));
});

// ── Defectos conocidos (acta de la semana 13) ─────────────────────────────────
defecto('D-01', 'H-01', 'RF-04', 'PAS 80 / PAD 120 se acepta con 201 (la diastólica supera a la sistólica)', function () { igual(201, http(api(base()), 'POST', toma(['pas' => 80, 'pad' => 120]))[0]); });
defecto('D-02', 'H-02', 'RNF-04', 'Niño de 5 años con FC 250: sin_rango y 0 alertas', function () {
    [$estado, $c] = http(api(base()), 'POST', toma(['fc' => 250], 'pac-nino', 'epi-2')); igual([201, ['fc' => 'sin_rango'], []], [$estado, $c['clasificaciones'], $c['alertas']]);
});
defecto('D-03', 'H-03', 'RNF-01', 'La misma petición enviada dos veces crea dos tomas', function () {
    $pdo = base(); $e = api($pdo); http($e, 'POST', toma(['fc' => 80])); http($e, 'POST', toma(['fc' => 80])); igual(2, (int) $pdo->query('SELECT COUNT(*) FROM tomas_signos_vitales')->fetchColumn());
});

printf("\nTotal: %d  Pasan: %d  Fallan: %d  Defectos abiertos: %d\n", array_sum($conteo), $conteo['PASA'], $conteo['FALLA'], $conteo['ABIERTO']);
exit($conteo['FALLA'] === 0 ? 0 : 1);
