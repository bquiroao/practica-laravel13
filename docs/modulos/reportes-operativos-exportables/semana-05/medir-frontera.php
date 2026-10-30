<?php
declare(strict_types=1);

// Evidencia de la semana 5 (sin base ni red):  php medir-frontera.php
// Usa solo el Dominio del Micro-HIS (semana 3) para medir la reproducibilidad y el costo de generar un archivo, y revisa el contrato y la propiedad de datos.
use Reportes\Dominio\{Archivo, Csv, Filtros};

require __DIR__ . '/../micro-his-reportes/src/Dominio/Reportes.php';

$conteo = ['PASA' => 0, 'FALLA' => 0];
function prueba(string $id, string $que, callable $cuerpo): void {
    try { $cuerpo(); $estado = 'PASA'; $detalle = ''; } catch (\Throwable $e) { $estado = 'FALLA'; $detalle = ' → ' . $e->getMessage(); }
    $GLOBALS['conteo'][$estado]++;
    printf("%-7s %-5s %s%s\n", $id, $estado, $que, $detalle);
}
function igual(mixed $esperado, mixed $real, string $que = 'valor'): void {
    if ($esperado !== $real) { throw new \RuntimeException("$que: esperado " . json_encode($esperado) . ', obtenido ' . json_encode($real)); } }
function filas(int $n): array {
    $f = [];
    for ($i = 0; $i < $n; $i++) { $f[] = ['paciente' => "Paciente $i", 'documento' => (string) (1000000 + $i), 'servicio' => ['medicina', 'cirugia', 'pediatria'][$i % 3], 'cama' => 'C' . ($i % 90), 'fecha' => '2026-10-' . sprintf('%02d', 1 + $i % 27)]; }
    return $f;
}
$censo = \Reportes\Dominio\catalogo()['censo'];
$csv = new Csv();

prueba('REP-01', 'Reproducible: filtros escritos distinto y filas en otro orden dan el mismo hash; un dato distinto da otro hash', function () use ($censo, $csv) {
    $a = Filtros::desde(['hasta' => '2026-10-27', 'servicio' => ' Cirugia ', 'desde' => '2026-10-01'], $censo, '2026-10-28');
    $b = Filtros::desde(['DESDE' => '2026-10-01', 'Servicio' => 'cirugia', 'hasta' => '2026-10-27'], $censo, '2026-10-28');
    igual($a->comoArreglo(), $b->comoArreglo(), 'filtros normalizados');
    $filas = filas(300);
    $x = Archivo::generar($censo, $filas, $csv, false);
    igual($x->hash, Archivo::generar($censo, array_reverse($filas), $csv, false)->hash, 'orden de las filas');
    $filas[7]['cama'] = 'ZZ';
    igual(false, $x->hash === Archivo::generar($censo, $filas, $csv, false)->hash, 'un dato cambiado');
});
prueba('REP-02', 'Datos personales: sin permiso el archivo no contiene nombre ni documento y su hash es distinto del completo', function () use ($censo, $csv) {
    $filas = filas(50);
    $sin = Archivo::generar($censo, $filas, $csv, false);
    igual(0, preg_match('/Paciente \d|1000\d{3}/', $sin->contenido), 'datos personales visibles');
    igual(false, $sin->hash === Archivo::generar($censo, $filas, $csv, true)->hash, 'mismo hash con y sin permiso');
});
$t5 = [];
for ($i = 0; $i < 200; $i++) { $t = hrtime(true); Archivo::generar($censo, filas(5000), $csv, false); $t5[] = (hrtime(true) - $t) / 1e6; }
sort($t5);
$p95 = round($t5[189], 1);
$t = hrtime(true);
Archivo::generar($censo, filas(50000), $csv, false);
$ms50 = round((hrtime(true) - $t) / 1e6);
$mb = round(memory_get_peak_usage(true) / 1048576);
prueba('RNF-03', "Costo: 5 000 filas p95 = $p95 ms; 50 000 filas = $ms50 ms; memoria máx. $mb MB (meta < 2000 ms por reporte de 92 días)", fn () => igual(true, $p95 < 2000 && $ms50 < 2000, 'tiempo'));
prueba('MED-01', 'Frontera: el módulo solo escribe exportaciones_reporte (propia) y lee 2 vistas de otros módulos; nunca UPDATE ni DELETE', function () {
    $pdo = (string) file_get_contents(__DIR__ . '/../micro-his-reportes/src/Persistencia/ReportesPdo.php');
    preg_match_all('/INSERT INTO (\w+)/', $pdo, $m);
    igual(['exportaciones_reporte'], array_values(array_unique($m[1])), 'tablas escritas');
    igual(0, preg_match('/\b(UPDATE|DELETE)\b/', $pdo), 'UPDATE o DELETE');
    igual(['vw_censo_servicio', 'vw_egresos'], array_values(array_map(fn ($r) => $r->vista, \Reportes\Dominio\catalogo())), 'vistas leídas');
});
prueba('CON-01', 'Contrato api/openapi.yaml: documenta cada código de error y cada estado de la API', function () {
    $c = (string) file_get_contents(__DIR__ . '/api/openapi.yaml');
    $faltan = array_filter(['sin_sesion', 'acceso_denegado', 'reporte_no_encontrado', 'exportacion_no_encontrada', 'formato_no_soportado', 'filtro_invalido', 'ruta_no_encontrada',
        'metodo_no_permitido', 'error_persistencia', '"200":', '"400":', '"401":', '"403":', '"404":', '"405":', '"422":', '"500":'], fn ($x) => !str_contains($c, $x));
    igual([], array_values($faltan), 'sin documentar');
});

printf("\nTotal: %d  Pasan: %d  Fallan: %d\n", array_sum($conteo), $conteo['PASA'], $conteo['FALLA']);
exit($conteo['FALLA'] === 0 ? 0 : 1);
