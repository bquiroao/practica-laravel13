<?php
declare(strict_types=1);

// Semana 15: pruebas unitarias (U), de caja blanca (W) y de caja negra (B) trazadas a RF/RNF.  php tests/semana-15.php
// Un defecto conocido (acta de la semana 13) se prueba con defecto(): ABIERTO mientras se reproduce; si deja de reproducirse, FALLA y pide pasarlo a prueba normal.
use Reportes\Aplicacion\{ExportarReporte, Usuario};
use Reportes\Dominio\{Archivo, Csv, DefinicionReporte, ErrorDominio, Filtros};
use Reportes\Persistencia\ReportesPdo;
use Reportes\Presentacion\{Enrutador, Peticion, ReportesControlador};
use function Reportes\Dominio\catalogo;

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
function caso(PDO $pdo): ExportarReporte { $r = new ReportesPdo($pdo); return new ExportarReporte($r, $r, catalogo(), ['csv' => new Csv()], fn () => '2026-10-25 12:00:00'); }
function api(PDO $pdo): Enrutador {
    return new Enrutador(new ReportesControlador(caso($pdo), ['jefa' => new Usuario('usr-jefa', 'jefatura', 'hosp-1', true), 'auditor' => new Usuario('usr-aud', 'auditor', 'hosp-1', false),
        'otra' => new Usuario('usr-otra', 'jefatura', 'hosp-2', true), 'medico' => new Usuario('usr-med', 'medico', 'hosp-1', true)]));
}
function http(Enrutador $e, string $metodo, string $ruta, array $q = [], string $usuario = 'jefa'): array { return $e->atender(new Peticion($metodo, $ruta, $q, $usuario)); }
function exportar(Enrutador $e, array $q = [], string $usuario = 'jefa', string $codigo = 'censo'): array { return http($e, 'GET', "/api/v1/reportes/$codigo/exportar", $q + ['desde' => '2026-10-01', 'hasta' => '2026-10-25'], $usuario); }
function lineas(string $csv): int { return count(explode("\n", $csv)) - 1; }
function total(PDO $pdo): int { return (int) $pdo->query('SELECT COUNT(*) FROM exportaciones_reporte')->fetchColumn(); }
$censo = catalogo()['censo'];

// ── Unitarias ─────────────────────────────────────────────────────────────────
prueba('U-01', 'RF-02, RF-10', 'Filtros::desde: 92 días se aceptan y 93 no; hasta = hoy se acepta y mañana no; desde > hasta se rechaza', function () use ($censo) {
    igual('2026-10-24', Filtros::desde(['desde' => '2026-07-25', 'hasta' => '2026-10-24'], $censo, '2026-10-25')->hasta);
    Filtros::desde(['desde' => '2026-07-25', 'hasta' => '2026-10-25'], $censo, '2026-10-25');
    foreach ([[['desde' => '2026-07-24', 'hasta' => '2026-10-25'], 'hasta'], [['desde' => '2026-10-01', 'hasta' => '2026-10-26'], 'hasta'], [['desde' => '2026-10-10', 'hasta' => '2026-10-01'], 'desde']] as [$f, $campo]) {
        $e = errorDe(fn () => Filtros::desde($f, $censo, '2026-10-25')); igual(['filtro_invalido', $campo], [$e->codigo, $e->campo]);
    }
});
prueba('U-02', 'RF-03', 'Filtros normaliza: « Cirugia » → cirugia, claves en minúscula y ordenadas', function () use ($censo) {
    $f = Filtros::desde(['HASTA' => '2026-10-25', 'Desde' => ' 2026-10-01 ', 'servicio' => ' Cirugia '], $censo, '2026-10-25');
    igual(['desde' => '2026-10-01', 'hasta' => '2026-10-25', 'servicio' => 'cirugia'], $f->comoArreglo());
});
prueba('U-03', 'RF-05', 'Csv: escapa comillas, comas y saltos de línea; sin filas deja solo el encabezado', function () {
    $csv = new Csv();
    igual("a,b\n\"x \"\"y\"\"\",\"1,2\"\n\"l1\nl2\",z", $csv->generar(['a', 'b'], [['x "y"', '1,2'], ["l1\nl2", 'z']]));
    igual('a,b', $csv->generar(['a', 'b'], []));
});
prueba('U-04', 'RF-06, RF-09, RNF-01', 'Archivo::generar: mismas filas en otro orden → mismo contenido y mismo hash; sin permiso enmascara paciente y documento', function () use ($censo) {
    $f1 = ['paciente' => 'Luis', 'documento' => '2', 'servicio' => 'cirugia', 'cama' => 'B2', 'fecha' => '2026-10-21']; $f2 = ['paciente' => 'Ana', 'documento' => '1', 'servicio' => 'medicina', 'cama' => 'A1', 'fecha' => '2026-10-20'];
    $a = Archivo::generar($censo, [$f1, $f2], new Csv(), true); $b = Archivo::generar($censo, [$f2, $f1], new Csv(), true);
    igual([$a->contenido, $a->hash, 2], [$b->contenido, $b->hash, $b->filas]); igual(hash('sha256', $a->contenido), $a->hash);
    $m = Archivo::generar($censo, [$f1], new Csv(), false); igual("paciente,documento,servicio,cama,fecha\n***,***,cirugia,B2,2026-10-21", $m->contenido);
});

// ── Caja blanca (ramas del código) ────────────────────────────────────────────
prueba('W-01', 'RF-02, RF-03, RF-10', 'Filtros::desde recorre cada rama de error: fecha vacía, texto, sin ceros, filtro con valor no permitido y filtro desconocido', function () use ($censo) {
    foreach ([['', 'desde'], ['ayer', 'desde'], ['2026-1-5', 'desde']] as [$d, $campo]) { igual($campo, errorDe(fn () => Filtros::desde(['desde' => $d, 'hasta' => '2026-10-25'], $censo, '2026-10-25'))->campo, "desde={$d}"); }
    igual('hasta', errorDe(fn () => Filtros::desde(['desde' => '2026-10-01'], $censo, '2026-10-25'))->campo, 'hasta ausente');
    igual('servicio', errorDe(fn () => Filtros::desde(['desde' => '2026-10-01', 'hasta' => '2026-10-25', 'servicio' => 'urgencias'], $censo, '2026-10-25'))->campo);
    igual('color', errorDe(fn () => Filtros::desde(['desde' => '2026-10-01', 'hasta' => '2026-10-25', 'color' => 'rojo'], $censo, '2026-10-25'))->campo);
});
prueba('W-02', 'RF-07, RF-10, RNF-02', 'ExportarReporte::ejecutar recorre sus guardas en orden (rol → reporte → formato → filtros) y ninguna deja un registro', function () {
    $pdo = base(); $c = caso($pdo); $q = ['desde' => '2026-10-01', 'hasta' => '2026-10-25'];
    igual('acceso_denegado', errorDe(fn () => $c->ejecutar('censo', $q, 'csv', new Usuario('m', 'medico', 'hosp-1', true)))->codigo);
    igual('reporte_no_encontrado', errorDe(fn () => $c->ejecutar('nada', $q, 'csv', new Usuario('j', 'jefatura', 'hosp-1', true)))->codigo);
    igual('formato_no_soportado', errorDe(fn () => $c->ejecutar('censo', $q, 'pdf', new Usuario('j', 'jefatura', 'hosp-1', true)))->codigo);
    igual('filtro_invalido', errorDe(fn () => $c->ejecutar('censo', ['desde' => 'x', 'hasta' => 'y'], 'csv', new Usuario('j', 'jefatura', 'hosp-1', true)))->codigo);
    igual(0, total($pdo), 'registros tras 4 rechazos');
});
prueba('W-03', 'RF-08, RNF-01, RNF-02', 'reproducir recorre sus ramas: inexistente, de otro hospital, coincide y no coincide cuando el dato fuente cambió antes del corte', function () {
    $pdo = base(); $c = caso($pdo); $jefa = new Usuario('j', 'jefatura', 'hosp-1', true); $x = $c->ejecutar('censo', ['desde' => '2026-10-01', 'hasta' => '2026-10-25'], 'csv', $jefa);
    igual('exportacion_no_encontrada', errorDe(fn () => $c->reproducir('exp-no-existe', $jefa))->codigo);
    igual('exportacion_no_encontrada', errorDe(fn () => $c->reproducir($x['id'], new Usuario('o', 'jefatura', 'hosp-2', true)))->codigo);
    igual(true, $c->reproducir($x['id'], $jefa)['coincide']);
    $pdo->exec("UPDATE vw_censo_servicio SET cama = 'Z9' WHERE paciente = 'Ana López'");   // corrección retroactiva: la fila sigue registrada antes del corte
    igual(false, $c->reproducir($x['id'], $jefa)['coincide'], 'tras editar una fila vieja');
});

// ── Caja negra (solo la API HTTP) ─────────────────────────────────────────────
prueba('B-01', 'RF-02, RF-10', 'Rango de 91 días → 200; de 100 días, futuro o invertido → 422 con el campo; nada se registra', function () {
    $pdo = base(); $e = api($pdo);
    igual(200, exportar($e, ['desde' => '2026-07-26', 'hasta' => '2026-10-25'])[0]);
    foreach ([[['desde' => '2026-07-17'], 'hasta'], [['hasta' => '2026-10-26'], 'hasta'], [['desde' => '2026-10-26', 'hasta' => '2026-10-25'], 'desde']] as [$q, $campo]) {
        [$estado, $c] = exportar($e, $q); igual([422, 'filtro_invalido', $campo], [$estado, $c['codigo'], $c['campo']]);
    }
    igual(1, total($pdo), 'solo la exportación correcta quedó registrada');
});
prueba('B-02', 'RF-03', 'Filtro opcional válido filtra (1 fila); valor no permitido o filtro desconocido → 422 con el campo', function () {
    $e = api(base()); igual([200, 1], [exportar($e, ['servicio' => 'cirugia'])[0], lineas(exportar($e, ['servicio' => 'cirugia'])[1])]);
    igual(['servicio'], [exportar($e, ['servicio' => 'urgencias'])[1]['campo']]); igual(['color'], [exportar($e, ['color' => 'rojo'])[1]['campo']]);
});
prueba('B-03', 'RF-05, RF-10', 'CSV por defecto con tipo, id y huella en cabeceras; pdf → 400; reporte inexistente → 404; método no permitido → 405', function () {
    $e = api(base()); [$estado, $cuerpo, $cab] = exportar($e);
    igual([200, 'text/csv; charset=utf-8'], [$estado, $cab['Content-Type']]); igual(hash('sha256', $cuerpo), $cab['X-Reporte-Sha256']); igual(true, str_starts_with($cab['X-Exportacion-Id'], 'exp-'));
    igual([400, 'formato_no_soportado'], [exportar($e, ['formato' => 'pdf'])[0], exportar($e, ['formato' => 'pdf'])[1]['codigo']]); igual(404, exportar($e, [], 'jefa', 'inventado')[0]);
    igual([405, 404], [http($e, 'DELETE', '/api/v1/reportes/censo/exportar')[0], http($e, 'GET', '/api/v1/nada')[0]]);
});
prueba('B-04', 'RF-07, RF-08, RNF-01', 'Exportar y luego reproducir por POST: coincide; una fila registrada después del corte no cambia la huella', function () {
    $pdo = base(); $e = api($pdo); [, , $cab] = exportar($e);
    $pdo->exec("INSERT INTO vw_censo_servicio VALUES ('hosp-1', 'Tardío', '4444', 'medicina', 'A2', '2026-10-21', '2026-10-25 15:00:00')");
    [$estado, $c] = http($e, 'POST', '/api/v1/exportaciones/' . $cab['X-Exportacion-Id'] . '/reproducir');
    igual([200, true, $cab['X-Reporte-Sha256']], [$estado, $c['coincide'], $c['hash']]); igual(1, total($pdo));
});
prueba('B-05', 'RF-06, RF-09, RNF-02', 'Auditor ve *** en datos personales; el hospital 2 no ve filas del 1 ni puede reproducir su exportación; el médico recibe 403', function () {
    $pdo = base(); $e = api($pdo); $csv = exportar($e, [], 'auditor')[1];
    igual(true, str_contains($csv, '***,***,cirugia') && !str_contains($csv, 'Luis') && !str_contains($csv, 'Otra'), 'enmascarado'); igual(true, str_contains(exportar($e, [], 'otra')[1], 'Otra Persona') && !str_contains(exportar($e, [], 'otra')[1], 'Luis'));
    [, , $cab] = exportar($e); igual(404, http($e, 'POST', '/api/v1/exportaciones/' . $cab['X-Exportacion-Id'] . '/reproducir', [], 'otra')[0]);
    igual([403, 401], [exportar($e, [], 'medico')[0], exportar($e, [], 'nadie')[0]]);
});

// ── Defectos conocidos (acta de la semana 13) ─────────────────────────────────
defecto('D-01', 'H-01', 'RF-02, RF-10', 'desde=2026-13-45 lanza una excepción sin capturar (no hay 422)', function () { igual(true, errorDe(fn () => exportar(api(base()), ['desde' => '2026-13-45', 'hasta' => '2026-13-46'])) instanceof \Exception); });
defecto('D-02', 'H-02', 'RNF-02', 'Una celda que empieza con = sale sin neutralizar en el CSV', function () {
    $pdo = base(); $pdo->exec("INSERT INTO vw_censo_servicio VALUES ('hosp-1', '=1+1', '5555', 'medicina', 'A3', '2026-10-22', '2026-10-22 08:00:00')");
    igual(true, in_array('=1+1', array_map(fn ($l) => str_getcsv($l, ',', '"', '')[0], explode("\n", exportar(api($pdo))[1])), true));
});
defecto('D-03', 'H-03', 'RF-02', 'desde=2026-02-31 (no existe) responde 200 con el reporte vacío', function () { [$estado, $csv] = exportar(api(base()), ['desde' => '2026-02-31', 'hasta' => '2026-03-02']); igual([200, 0], [$estado, lineas($csv)]); });

printf("\nTotal: %d  Pasan: %d  Fallan: %d  Defectos abiertos: %d\n", array_sum($conteo), $conteo['PASA'], $conteo['FALLA'], $conteo['ABIERTO']);
exit($conteo['FALLA'] === 0 ? 0 : 1);
